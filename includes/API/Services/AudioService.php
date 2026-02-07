<?php
/**
 * Audio Service
 *
 * 处理音频转录和语音合成
 *
 * @package WPMind
 * @subpackage API\Services
 * @since 3.7.0
 */

declare(strict_types=1);

namespace WPMind\API\Services;

use WP_Error;

/**
 * Audio Service
 *
 * @since 3.7.0
 */
class AudioService extends AbstractService {

	/**
	 * 音频转录（语音转文字）
	 *
	 * @since 2.7.0
	 * @param string $audio_file 音频文件路径或 URL
	 * @param array  $options    选项
	 * @return array|WP_Error
	 */
	public function transcribe(string $audio_file, array $options = []) {
		$defaults = [
			'context'  => 'transcription',
			'language' => 'auto',
			'prompt'   => '',
			'format'   => 'text',
			'provider' => 'auto',
		];
		$options = wp_parse_args($options, $defaults);

		$context = $options['context'];
		$transcribe_providers = ['openai'];

		$provider = $this->resolve_provider($options['provider'], $context);

		// 文件大小上限（25MB，与 OpenAI Whisper API 限制一致）
		$max_file_size = apply_filters('wpmind_transcribe_max_file_size', 25 * MB_IN_BYTES);

		// 允许的音频文件扩展名
		$allowed_extensions = ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm', 'ogg', 'flac'];

		// 准备文件内容（在 failover 循环外处理，避免重复下载/IO）
		$is_temp = false;
		if (filter_var($audio_file, FILTER_VALIDATE_URL)) {
			// URL 安全验证：拒绝内网地址，防止 SSRF
			if (!wp_http_validate_url($audio_file)) {
				return new WP_Error('wpmind_invalid_url', __('URL 验证失败：不允许访问内网地址', 'wpmind'));
			}

			// 协议白名单
			$scheme = wp_parse_url($audio_file, PHP_URL_SCHEME);
			if (!in_array($scheme, ['http', 'https'], true)) {
				return new WP_Error('wpmind_invalid_url', __('仅支持 HTTP/HTTPS 协议', 'wpmind'));
			}

			$temp_file = download_url($audio_file);
			if (is_wp_error($temp_file)) {
				return $temp_file;
			}
			$file_path = $temp_file;
			$is_temp = true;
		} else {
			// 本地文件路径安全验证：限制在 uploads 目录内
			$upload_dir = wp_upload_dir();
			$realpath = realpath($audio_file);
			$basedir = realpath($upload_dir['basedir']);

			if ($realpath === false || $basedir === false || strpos($realpath, $basedir) !== 0) {
				return new WP_Error('wpmind_invalid_path', __('文件路径必须在 uploads 目录内', 'wpmind'));
			}

			$file_path = $realpath;
		}

		if (!file_exists($file_path)) {
			if ($is_temp && isset($temp_file) && file_exists($temp_file)) {
				unlink($temp_file);
			}
			return new WP_Error('wpmind_file_not_found', __('音频文件不存在', 'wpmind'));
		}

		// 文件扩展名验证
		$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
		if (!in_array($extension, $allowed_extensions, true)) {
			if ($is_temp && isset($temp_file) && file_exists($temp_file)) {
				unlink($temp_file);
			}
			return new WP_Error('wpmind_invalid_filetype',
				sprintf(__('不支持的音频格式: %s', 'wpmind'), $extension));
		}

		// 文件大小验证
		$file_size = filesize($file_path);
		if ($file_size === false || $file_size > $max_file_size) {
			if ($is_temp && isset($temp_file) && file_exists($temp_file)) {
				unlink($temp_file);
			}
			return new WP_Error('wpmind_file_too_large',
				sprintf(__('文件大小超过限制 (%s)', 'wpmind'), size_format($max_file_size)));
		}

		$file_content = file_get_contents($file_path);

		if ($is_temp && isset($temp_file) && file_exists($temp_file)) {
			unlink($temp_file);
		}

		do_action('wpmind_before_request', 'transcribe', compact('audio_file', 'options'), $context);

		return $this->execute_with_failover('transcribe', $provider, $context, function (string $try_provider, array $endpoint) use ($file_content, $options, $audio_file) {
			$api_key = $endpoint['api_key'];
			$base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
			$api_url = trailingslashit($base_url) . 'audio/transcriptions';

			$boundary = wp_generate_password(24, false);
			$body = '';

			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"file\"; filename=\"audio.mp3\"\r\n";
			$body .= "Content-Type: audio/mpeg\r\n\r\n";
			$body .= $file_content . "\r\n";

			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
			$body .= "whisper-1\r\n";

			if ($options['language'] !== 'auto') {
				$body .= "--{$boundary}\r\n";
				$body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
				$body .= "{$options['language']}\r\n";
			}

			if (!empty($options['prompt'])) {
				$body .= "--{$boundary}\r\n";
				$body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
				$body .= "{$options['prompt']}\r\n";
			}

			$response_format = $options['format'] === 'text' ? 'text' : $options['format'];
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
			$body .= "{$response_format}\r\n";

			$body .= "--{$boundary}--\r\n";

			$start_time = microtime(true);

			$response = wp_remote_post($api_url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				],
				'body'    => $body,
				'timeout' => 120,
			]);

			$latency_ms = (int)((microtime(true) - $start_time) * 1000);

			if (is_wp_error($response)) {
				$this->record_result($try_provider, false, $latency_ms);
				return new WP_Error('wpmind_transcribe_failed',
					sprintf(__('转录请求失败: %s', 'wpmind'), $response->get_error_message()));
			}

			$status_code = wp_remote_retrieve_response_code($response);
			$resp_body = wp_remote_retrieve_body($response);

			if ($status_code !== 200) {
				$this->record_result($try_provider, false, $latency_ms);
				$data = json_decode($resp_body, true);
				$error_message = $data['error']['message'] ?? $resp_body;
				return new WP_Error('wpmind_transcribe_error',
					sprintf(__('转录 API 错误 (%d): %s', 'wpmind'), $status_code, $error_message));
			}

			$this->record_result($try_provider, true, $latency_ms);

			$result = [
				'text'     => $options['format'] === 'text' ? $resp_body : '',
				'data'     => $options['format'] !== 'text' ? json_decode($resp_body, true) : null,
				'provider' => $try_provider,
				'format'   => $options['format'],
			];

			if ($options['format'] !== 'text' && is_array($result['data'])) {
				$result['text'] = $result['data']['text'] ?? '';
			}

			do_action('wpmind_after_request', 'transcribe', $result, compact('audio_file', 'options'), []);

			return $result;
		}, $transcribe_providers);
	}

	/**
	 * 文本转语音
	 *
	 * @since 2.7.0
	 * @param string $text    要转换的文本
	 * @param array  $options 选项
	 * @return array|WP_Error
	 */
	public function speech(string $text, array $options = []) {
		$defaults = [
			'context'  => 'speech',
			'voice'    => 'alloy',
			'model'    => 'tts-1',
			'speed'    => 1.0,
			'format'   => 'mp3',
			'save_to'  => '',
			'provider' => 'auto',
		];
		$options = wp_parse_args($options, $defaults);

		$context = $options['context'];
		$speech_providers = ['openai', 'deepseek'];

		$provider = $this->resolve_provider($options['provider'], $context);

		do_action('wpmind_before_request', 'speech', compact('text', 'options'), $context);

		return $this->execute_with_failover('speech', $provider, $context, function (string $try_provider, array $endpoint) use ($text, $options) {
			$api_key = $endpoint['api_key'];
			$base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
			$api_url = trailingslashit($base_url) . 'audio/speech';

			$start_time = microtime(true);

			$response = wp_remote_post($api_url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode([
					'model'           => $options['model'],
					'input'           => $text,
					'voice'           => $options['voice'],
					'speed'           => $options['speed'],
					'response_format' => $options['format'],
				]),
				'timeout' => 60,
			]);

			$latency_ms = (int)((microtime(true) - $start_time) * 1000);

			if (is_wp_error($response)) {
				$this->record_result($try_provider, false, $latency_ms);
				return new WP_Error('wpmind_speech_failed',
					sprintf(__('语音合成请求失败: %s', 'wpmind'), $response->get_error_message()));
			}

			$status_code = wp_remote_retrieve_response_code($response);
			$audio_data = wp_remote_retrieve_body($response);

			if ($status_code !== 200) {
				$this->record_result($try_provider, false, $latency_ms);
				$data = json_decode($audio_data, true);
				$error_message = $data['error']['message'] ?? __('未知错误', 'wpmind');
				return new WP_Error('wpmind_speech_error',
					sprintf(__('语音合成 API 错误 (%d): %s', 'wpmind'), $status_code, $error_message));
			}

			$this->record_result($try_provider, true, $latency_ms);

			$result = [
				'provider' => $try_provider,
				'model'    => $options['model'],
				'voice'    => $options['voice'],
				'format'   => $options['format'],
				'size'     => strlen($audio_data),
			];

			if (!empty($options['save_to'])) {
				$upload_dir = wp_upload_dir();
				$save_dir = realpath(dirname($options['save_to']));
				$base_dir = realpath($upload_dir['basedir']);
				if ($save_dir === false || $base_dir === false || strpos($save_dir, $base_dir) !== 0) {
					return new WP_Error('wpmind_invalid_path', __('保存路径必须在 uploads 目录内', 'wpmind'));
				}
				$written = file_put_contents($options['save_to'], $audio_data);
				if ($written === false) {
					return new WP_Error('wpmind_write_failed', __('文件写入失败', 'wpmind'));
				}
				$result['file'] = $options['save_to'];
			} else {
				$upload = wp_upload_bits(
					'wpmind-speech-' . time() . '.' . $options['format'],
					null,
					$audio_data
				);

				if (!empty($upload['error'])) {
					return new WP_Error('wpmind_upload_failed', $upload['error']);
				}

				$result['url'] = $upload['url'];
				$result['file'] = $upload['file'];
			}

			do_action('wpmind_after_request', 'speech', $result, compact('text', 'options'), []);

			return $result;
		}, $speech_providers);
	}
}
