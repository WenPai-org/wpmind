<?php
/**
 * RemoteModelLister unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Catalog\RemoteModelLister;

/**
 * @covers \WPMind\Provider\Catalog\RemoteModelLister
 */
final class RemoteModelListerTest extends TestCase {

	public function test_chat_candidates_drop_non_chat_modalities(): void {
		$lister = new RemoteModelLister();

		$ids = [
			'wan2.7-image',
			'qwen-audio-3.0-tts-plus',
			'deepseek-v4-pro',
			'qwen3.7-max',
			'embedding-v4',
			'glm-5.3',
			'wan3.0-video',
			'qwen3.8-flash',
		];

		$this->assertSame(
			[ 'deepseek-v4-pro', 'qwen3.7-max', 'glm-5.3', 'qwen3.8-flash' ],
			$lister->chat_candidates( $ids )
		);
	}

	public function test_chat_candidates_preserve_order_and_empty_input(): void {
		$lister = new RemoteModelLister();

		$this->assertSame( [], $lister->chat_candidates( [] ) );
		$this->assertSame( [ 'b-model', 'a-model' ], $lister->chat_candidates( [ 'b-model', 'a-model' ] ) );
	}

	public function test_chat_candidates_do_not_drop_legitimate_chat_names(): void {
		$lister = new RemoteModelLister();

		// Names merely containing letters from the blacklist must survive.
		$ids = [ 'qwen3-vl-plus', 'glm-4v-flash', 'auto', 'step-3' ];
		$this->assertSame( $ids, $lister->chat_candidates( $ids ) );
	}
}
