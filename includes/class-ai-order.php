<?php
/**
 * Filename: class-ai-order.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 04/05/2026
 * Last Modified: 04/05/2026
 * Description: OpenAI-compatible chat API client to reorder post IDs for series spacing (DeepSeek default).
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
 * Calls an OpenAI-compatible Chat Completions endpoint (e.g. DeepSeek V4) to return a
 * permutation of post IDs that spaces similar titles when possible. Publish times
 * are still assigned by Schedulely_Scheduler.
 *
 * @see https://apidog.com/blog/how-to-use-deepseek-v4-api/
 */
class Schedulely_AI_Order
{

    /**
     * Reorder post IDs using the configured LLM, or return WP_Error on failure.
     *
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

        $timeout = (int) apply_filters('schedulely_ai_request_timeout', 120);
        if ($timeout < 30) {
            $timeout = 30;
        }
        if ($timeout > 300) {
            $timeout = 300;
        }

        $response = wp_remote_post(
            $url,
            [
                'timeout' => $timeout,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
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
                    __('AI request failed (HTTP %1$s): %2$s', 'schedulely'),
                    (string) $code,
                    $this->excerpt_error_body($raw)
                )
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return new WP_Error(
                'schedulely_ai_json',
                __('AI response was not valid JSON.', 'schedulely')
            );
        }

        $content = $this->extract_message_content($decoded);
        if ('' === $content) {
            return new WP_Error(
                'schedulely_ai_empty',
                __('AI returned an empty message.', 'schedulely')
            );
        }

        $ordered = $this->parse_ordered_ids_from_content($content);
        if (is_wp_error($ordered)) {
            return $ordered;
        }

        if (!$this->is_valid_permutation($post_ids, $ordered)) {
            return new WP_Error(
                'schedulely_ai_perm',
                __('AI response did not contain the same post IDs as the input list.', 'schedulely')
            );
        }

        return $ordered;
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
            . 'Identify posts that clearly belong to the same article series or template (e.g. same topic with different states or locations). '
            . 'Return a JSON object with a single key "ordered_ids" whose value is an array of integers: '
            . 'every input post ID exactly once, in an order that avoids placing obvious same-series titles next to each other when the mix allows. '
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
     * Extract assistant text from OpenAI-shaped response.
     *
     * @param array<string,mixed> $decoded Decoded JSON.
     * @return string
     */
    private function extract_message_content(array $decoded)
    {
        if (!isset($decoded['choices'][0]['message']['content'])) {
            return '';
        }
        $content = $decoded['choices'][0]['message']['content'];
        return is_string($content) ? $content : '';
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
        $default = 'https://api.deepseek.com/v1';
        $url = get_option('schedulely_ai_base_url', $default);
        if (!is_string($url) || '' === trim($url)) {
            $url = $default;
        }
        $url = esc_url_raw($url);
        if ('' === $url || 0 !== strpos($url, 'https://')) {
            $url = $default;
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
        $model = get_option('schedulely_ai_model', 'deepseek-v4-flash');
        if (!is_string($model) || '' === trim($model)) {
            return 'deepseek-v4-flash';
        }

        return sanitize_text_field(substr(trim($model), 0, 120));
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
