<?php
/**
 * Filename: class-ai-order.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 04/05/2026
 * Last Modified: 03/05/2026
 * Description: OpenAI-compatible chat API client to reorder post IDs for series spacing.
 *
 * @package Schedulely
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Schedulely_AI_Order
 *
 * Calls an OpenAI-compatible Chat Completions endpoint to return a permutation of post IDs
 * that spaces similar titles when possible. Publish times are still assigned by Schedulely_Scheduler.
 */
class Schedulely_AI_Order
{

    /**
     * Reorder post IDs using the configured LLM, or return WP_Error on failure.
     *
     * @since 1.4.0
     * @param array $post_ids List of positive post IDs.
     * @return array<int>|WP_Error Ordered IDs (same length as input) or error.
     */
    public function reorder_post_ids(array $post_ids)
    {
        $post_ids = array_values(array_filter(array_map('absint', $post_ids)));

        if (count($post_ids) < 2) {
            return $post_ids;
        }

        $api_key = apply_filters('schedulely_ai_api_key', get_option('schedulely_ai_api_key', ''));
        if ('' === trim((string) $api_key)) {
            return new WP_Error(
                'schedulely_ai_no_key',
                __('No API key is configured for AI queue ordering.', 'schedulely')
            );
        }

        $base = $this->get_api_base_url();
        $model = $this->get_model();
        $url = rtrim($base, '/') . '/chat/completions';

        $lines = [];
        foreach ($post_ids as $post_id) {
            $title = get_post_field('post_title', $post_id, 'raw');
            if (!is_string($title)) {
                $title = '';
            }
            $title = wp_strip_all_tags($title);
            $lines[] = (string) $post_id . "\t" . $title;
        }

        $body = $this->build_request_body($model, $lines, count($post_ids));
        $body = apply_filters('schedulely_ai_chat_completions_body', $body, $post_ids);

        $post_count = count($post_ids);
        // Large queues need longer completions; default scales ~0.45s per post, 120–540s before filter.
        $default_timeout = max(120, min(540, 60 + (int) round($post_count * 0.45)));
        $timeout = (int) apply_filters('schedulely_ai_request_timeout', $default_timeout, $post_ids);
        if ($timeout < 30) {
            $timeout = 30;
        }
        $max_timeout = (int) apply_filters('schedulely_ai_request_timeout_max', 600);
        if ($max_timeout < 120) {
            $max_timeout = 120;
        }
        if ($max_timeout > 900) {
            $max_timeout = 900;
        }
        if ($timeout > $max_timeout) {
            $timeout = $max_timeout;
        }

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => $timeout,
                'headers' => $this->build_ai_http_headers($api_key),
                'body' => wp_json_encode($body),
            )
        );

        if (is_wp_error($response)) {
            $this->log_ai_reorder_attempt(
                array(
                    'outcome' => 'error',
                    'model' => $model,
                    'post_count' => count($post_ids),
                    'http_code' => null,
                    'usage_total_tokens' => null,
                    'error_code' => $response->get_error_code(),
                    'error_message' => schedulely_ai_log_sanitize_excerpt($response->get_error_message(), 500),
                    'assistant_excerpt' => '',
                    'raw_excerpt' => '',
                    'note' => __('HTTP transport error before a response body was received (no provider tokens for completion).', 'schedulely'),
                )
            );

            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        if (!is_string($raw)) {
            $raw = '';
        }

        $decoded = json_decode($raw, true);
        $usage_tokens = null;
        if (is_array($decoded) && isset($decoded['usage']['total_tokens'])) {
            $usage_tokens = (int) $decoded['usage']['total_tokens'];
        }

        if (200 !== $code) {
            $err = new WP_Error(
                'schedulely_ai_http',
                sprintf(
                    /* translators: 1: HTTP status code, 2: response body excerpt */
                    __('AI request failed (HTTP %1$s): %2$s', 'schedulely'),
                    (string) $code,
                    $this->excerpt_error_body($raw)
                )
            );
            $this->log_ai_reorder_attempt(
                array(
                    'outcome' => 'error',
                    'model' => $model,
                    'post_count' => count($post_ids),
                    'http_code' => $code,
                    'usage_total_tokens' => $usage_tokens,
                    'error_code' => 'schedulely_ai_http',
                    'error_message' => schedulely_ai_log_sanitize_excerpt($err->get_error_message(), 500),
                    'assistant_excerpt' => '',
                    'raw_excerpt' => schedulely_ai_log_sanitize_excerpt($raw, 2000),
                    'note' => __('Non-200 response; provider may or may not report token usage depending on the API.', 'schedulely'),
                )
            );

            return $err;
        }

        if (!is_array($decoded)) {
            $err = new WP_Error(
                'schedulely_ai_json',
                __('AI response was not valid JSON.', 'schedulely')
            );
            $this->log_ai_reorder_attempt(
                array(
                    'outcome' => 'error',
                    'model' => $model,
                    'post_count' => count($post_ids),
                    'http_code' => $code,
                    'usage_total_tokens' => $usage_tokens,
                    'error_code' => 'schedulely_ai_json',
                    'error_message' => schedulely_ai_log_sanitize_excerpt($err->get_error_message(), 500),
                    'assistant_excerpt' => '',
                    'raw_excerpt' => schedulely_ai_log_sanitize_excerpt($raw, 2000),
                    'note' => __('Body was not valid JSON after HTTP 200.', 'schedulely'),
                )
            );

            return $err;
        }

        $content = $this->extract_assistant_text_from_completion($decoded);
        if ('' === $content) {
            $err = new WP_Error(
                'schedulely_ai_empty',
                __('AI returned an empty message.', 'schedulely')
            );
            $this->log_ai_reorder_attempt(
                array(
                    'outcome' => 'error',
                    'model' => $model,
                    'post_count' => count($post_ids),
                    'http_code' => $code,
                    'usage_total_tokens' => $usage_tokens,
                    'error_code' => 'schedulely_ai_empty',
                    'error_message' => schedulely_ai_log_sanitize_excerpt($err->get_error_message(), 500),
                    'assistant_excerpt' => '',
                    'raw_excerpt' => schedulely_ai_log_sanitize_excerpt($raw, 2000),
                    'note' => __('Provider may still report tokens if the model ran but no assistant text was parsed.', 'schedulely'),
                )
            );

            return $err;
        }

        $ordered = $this->parse_ordered_ids_from_content($content);
        if (is_wp_error($ordered)) {
            $this->log_ai_reorder_attempt(
                array(
                    'outcome' => 'error',
                    'model' => $model,
                    'post_count' => count($post_ids),
                    'http_code' => $code,
                    'usage_total_tokens' => $usage_tokens,
                    'error_code' => $ordered->get_error_code(),
                    'error_message' => schedulely_ai_log_sanitize_excerpt($ordered->get_error_message(), 500),
                    'assistant_excerpt' => schedulely_ai_log_sanitize_excerpt($content, 2000),
                    'raw_excerpt' => schedulely_ai_log_sanitize_excerpt($raw, 1200),
                    'note' => __('Assistant text could not be parsed into ordered_ids JSON.', 'schedulely'),
                )
            );

            return $ordered;
        }

        if (!$this->is_valid_permutation($post_ids, $ordered)) {
            $err = new WP_Error(
                'schedulely_ai_perm',
                __('AI response did not contain the same post IDs as the input list.', 'schedulely')
            );
            $this->log_ai_reorder_attempt(
                array(
                    'outcome' => 'error',
                    'model' => $model,
                    'post_count' => count($post_ids),
                    'http_code' => $code,
                    'usage_total_tokens' => $usage_tokens,
                    'error_code' => 'schedulely_ai_perm',
                    'error_message' => schedulely_ai_log_sanitize_excerpt($err->get_error_message(), 500),
                    'assistant_excerpt' => schedulely_ai_log_sanitize_excerpt($content, 2000),
                    'raw_excerpt' => schedulely_ai_log_sanitize_excerpt($raw, 800),
                    'note' => sprintf(
                        /* translators: 1: number of IDs returned, 2: number of input posts */
                        __('Parsed %1$d IDs in ordered_ids; multiset does not match %2$d input IDs.', 'schedulely'),
                        count($ordered),
                        count($post_ids)
                    ),
                )
            );

            return $err;
        }

        $this->log_ai_reorder_attempt(
            array(
                'outcome' => 'success',
                'model' => $model,
                'post_count' => count($post_ids),
                'http_code' => $code,
                'usage_total_tokens' => $usage_tokens,
                'error_code' => '',
                'error_message' => '',
                'assistant_excerpt' => schedulely_ai_log_sanitize_excerpt($content, 1200),
                'raw_excerpt' => '',
                'note' => __('Queue order was applied.', 'schedulely'),
            )
        );

        return $ordered;
    }

    /**
     * Store a queue-reorder log row (Tools → Schedulely + optional WP_DEBUG_LOG).
     *
     * @since 1.5.4
     * @param array<string, mixed> $entry Log payload.
     */
    private function log_ai_reorder_attempt(array $entry)
    {
        if (!function_exists('schedulely_append_ai_reorder_log')) {
            return;
        }

        schedulely_append_ai_reorder_log($entry);
    }

    /**
     * Send a minimal Chat Completions request to verify URL, model, and API key.
     *
     * @since 1.4.3
     * @return array<string,string>|WP_Error On success: array with key 'message' for the admin UI.
     */
    public function test_api_connection()
    {
        $api_key = apply_filters('schedulely_ai_api_key', get_option('schedulely_ai_api_key', ''));
        if ('' === trim((string) $api_key)) {
            return new WP_Error(
                'schedulely_ai_no_key',
                __('No API key is configured.', 'schedulely')
            );
        }

        $base = $this->get_api_base_url();
        $model = $this->get_model();
        $url = rtrim($base, '/') . '/chat/completions';

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Reply with the single word: ok',
                ],
            ],
            'max_tokens' => 32,
            'temperature' => 0.2,
        ];
        $body = apply_filters('schedulely_ai_test_connection_body', $body);

        $timeout = (int) apply_filters('schedulely_ai_test_request_timeout', 45);
        if ($timeout < 15) {
            $timeout = 15;
        }
        if ($timeout > 120) {
            $timeout = 120;
        }

        $response = wp_remote_post(
            $url,
            [
                'timeout' => $timeout,
                'headers' => $this->build_ai_http_headers($api_key),
                'body' => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);

        if (200 !== (int) $code) {
            return new WP_Error(
                'schedulely_ai_http',
                sprintf(
                    /* translators: 1: HTTP status code, 2: response body excerpt */
                    __('Connection test failed (HTTP %1$s): %2$s', 'schedulely'),
                    (string) $code,
                    $this->excerpt_error_body($raw)
                )
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return new WP_Error(
                'schedulely_ai_test_json',
                sprintf(
                    /* translators: %s: response excerpt */
                    __('HTTP 200 but the body was not JSON. Excerpt: %s', 'schedulely'),
                    $this->excerpt_error_body($raw)
                )
            );
        }

        if (isset($decoded['error'])) {
            $err_msg = '';
            if (is_array($decoded['error'])) {
                $err_msg = isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : wp_json_encode($decoded['error']);
            } else {
                $err_msg = (string) $decoded['error'];
            }

            return new WP_Error(
                'schedulely_ai_api_error',
                sprintf(
                    /* translators: %s: API error message */
                    __('API returned an error object: %s', 'schedulely'),
                    $err_msg
                )
            );
        }

        $summary = $this->parse_completion_test_summary($decoded);

        if ('' !== $summary['text']) {
            return [
                'message' => __('Connection OK — the API returned an assistant message.', 'schedulely'),
            ];
        }

        if ($summary['has_usage']) {
            return [
                'message' => sprintf(
                    /* translators: %d: total tokens reported by the API */
                    __('Connection OK — the API accepted the request (reported %d total tokens). The reply text field was empty, which can happen with some models or modes.', 'schedulely'),
                    (int) $summary['total_tokens']
                ),
            ];
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            schedulely_log_error('Schedulely AI test: unexpected 200 response shape', [
                'excerpt' => $this->excerpt_error_body($raw),
            ]);
        }

        return new WP_Error(
            'schedulely_ai_test_shape',
            sprintf(
                /* translators: %s: short excerpt of the raw response body */
                __('HTTP 200 but no assistant text or usage data was found. Check the Base URL (must match your provider’s Chat Completions endpoint) and model id. Response excerpt: %s', 'schedulely'),
                $this->excerpt_error_body($raw)
            )
        );
    }

    /**
     * Best-effort assistant text from a Chat Completions JSON body (legacy `text`, string or multimodal `message.content`, `reasoning_content`).
     *
     * @since 1.4.7
     * @param array<string,mixed> $decoded Decoded JSON.
     * @return string Trimmed text, or empty string.
     */
    private function extract_assistant_text_from_completion(array $decoded)
    {
        $text = '';
        if (empty($decoded['choices'][0]) || !is_array($decoded['choices'][0])) {
            return '';
        }

        $choice = $decoded['choices'][0];
        if (isset($choice['text']) && is_string($choice['text']) && '' !== trim($choice['text'])) {
            $text = trim($choice['text']);
        }

        $msg = isset($choice['message']) && is_array($choice['message']) ? $choice['message'] : null;
        if (null !== $msg) {
            $content = $msg['content'] ?? null;
            if (is_string($content) && '' !== trim($content)) {
                $text = trim($content);
            } elseif (is_array($content)) {
                $parts = [];
                foreach ($content as $part) {
                    if (is_array($part) && isset($part['text'])) {
                        $parts[] = (string) $part['text'];
                    }
                }
                $text = trim(implode('', $parts));
            }
            if ('' === $text && isset($msg['reasoning_content']) && is_string($msg['reasoning_content']) && '' !== trim($msg['reasoning_content'])) {
                $text = trim($msg['reasoning_content']);
            }
        }

        return $text;
    }

    /**
     * Extract assistant-visible text and usage from a chat completion JSON body.
     *
     * @since 1.4.6
     * @param array<string,mixed> $decoded Decoded JSON.
     * @return array{text: string, has_usage: bool, total_tokens: int}
     */
    private function parse_completion_test_summary(array $decoded)
    {
        $text = $this->extract_assistant_text_from_completion($decoded);
        $total_tokens = isset($decoded['usage']['total_tokens']) ? (int) $decoded['usage']['total_tokens'] : 0;
        $has_usage = $total_tokens > 0;

        return [
            'text' => $text,
            'has_usage' => $has_usage,
            'total_tokens' => $total_tokens,
        ];
    }

    /**
     * Build Chat Completions JSON body (OpenAI-compatible).
     *
     * @param string   $model  Model id.
     * @param string[] $lines  Lines "id\ttitle".
     * @param int      $count  Number of posts.
     * @return array<string,mixed>
     */
    private function build_request_body($model, array $lines, $count)
    {
        // English-only instructions for model reliability (not passed through gettext).
        $system = 'You reorder WordPress posts for publication. Each line is: numeric_post_id TAB title. '
            . 'Detect posts that belong to the same series or template (for example: same topic with different states, locations, or repeated phrasing). '
            . 'Your goal is to maximize variety in the sequence. '
            . 'Do not place similar or same-series posts close together. Maintain a minimum spacing of at least 3 to 5 other posts between similar titles whenever the mix allows. '
            . 'If perfect spacing is not possible, distribute similar posts as evenly as you can across the entire list. '
            . 'Prioritize diversity of topics over original order. '
            . 'Return a JSON object with a single key "ordered_ids" whose value is an array of integers: every input post ID exactly once. '
            . 'Output only valid JSON, no markdown fences, no commentary.';

        $user = sprintf(
            "Reorder these %d posts. Respond with JSON only: {\"ordered_ids\":[...]}\n\n",
            $count
        );
        $user .= implode("\n", $lines);

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system,
                ],
                [
                    'role' => 'user',
                    'content' => $user,
                ],
            ],
            'temperature' => 1.0,
            'top_p' => 1.0,
            'max_tokens' => (int) apply_filters('schedulely_ai_max_output_tokens', 16384),
        ];

        $body['response_format'] = [
            'type' => 'json_object',
        ];

        return $body;
    }

    /**
     * Parse ordered_ids from model content (JSON object, possibly wrapped in fences).
     *
     * @param string $content Raw assistant message.
     * @return array<int>|WP_Error
     */
    private function parse_ordered_ids_from_content($content)
    {
        $content = trim($content);
        if (preg_match('/^```(?:json)?\s*(\{.*\})\s*```$/s', $content, $m)) {
            $content = $m[1];
        }

        $obj = json_decode($content, true);
        if (!is_array($obj) || !isset($obj['ordered_ids']) || !is_array($obj['ordered_ids'])) {
            return new WP_Error(
                'schedulely_ai_shape',
                __('AI JSON must contain an "ordered_ids" array.', 'schedulely')
            );
        }

        $out = [];
        foreach ($obj['ordered_ids'] as $id) {
            $out[] = absint($id);
        }

        return $out;
    }

    /**
     * Check same multiset of IDs.
     *
     * @param array<int> $original Original IDs.
     * @param array<int> $ordered   Candidate order.
     * @return bool
     */
    private function is_valid_permutation(array $original, array $ordered)
    {
        if (count($original) !== count($ordered)) {
            return false;
        }

        $a = array_map('intval', $original);
        $b = array_map('intval', $ordered);
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * Sanitized API base URL.
     *
     * @return string
     */
    private function get_api_base_url()
    {
        $builtin = 'https://api.deepseek.com/v1';
        $fallback = (string) apply_filters('schedulely_ai_default_base_url', $builtin);
        $stored = get_option('schedulely_ai_base_url', $fallback);
        $url = is_string($stored) ? trim($stored) : '';
        if ('' === $url) {
            return untrailingslashit($fallback);
        }
        $url = esc_url_raw($url);
        if ('' === $url || 0 !== strpos($url, 'https://')) {
            return untrailingslashit($fallback);
        }

        return untrailingslashit($url);
    }

    /**
     * Model id (OpenAI-compatible name for the provider).
     *
     * @return string
     */
    private function get_model()
    {
        $builtin = 'deepseek-v4-flash';
        $fallback = (string) apply_filters('schedulely_ai_default_model', $builtin);
        $model = get_option('schedulely_ai_model', $fallback);
        if (!is_string($model) || '' === trim($model)) {
            return $fallback;
        }

        return sanitize_text_field(substr(trim($model), 0, 120));
    }

    /**
     * HTTP headers for Chat Completions requests.
     *
     * @param string $api_key Bearer token (not logged).
     * @return array<string, string>
     */
    private function build_ai_http_headers($api_key)
    {
        $ver = defined('SCHEDULELY_VERSION') ? SCHEDULELY_VERSION : '1.0';

        return [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => (string) apply_filters(
                'schedulely_ai_http_user_agent',
                'Schedulely/' . $ver . '; ' . home_url('/')
            ),
        ];
    }

    /**
     * Shorten error body for messages.
     *
     * @param string $raw Body.
     * @return string
     */
    private function excerpt_error_body($raw)
    {
        if (!is_string($raw)) {
            return '';
        }
        $raw = wp_strip_all_tags($raw);
        if (strlen($raw) > 200) {
            return substr($raw, 0, 200) . '…';
        }

        return $raw;
    }
}
