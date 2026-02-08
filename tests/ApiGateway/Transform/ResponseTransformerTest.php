<?php
/**
 * Tests for ResponseTransformer
 *
 * @package WPMind\Tests\ApiGateway\Transform
 */

declare(strict_types=1);

namespace WPMind\Tests\ApiGateway\Transform;

require_once __DIR__ . '/../../../modules/api-gateway/includes/Transform/ResponseTransformer.php';

use WPMind\Modules\ApiGateway\Transform\ResponseTransformer;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ResponseTransformer format conversion.
 */
class ResponseTransformerTest extends TestCase {

	/**
	 * @var ResponseTransformer
	 */
	private ResponseTransformer $transformer;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		$this->transformer = new ResponseTransformer();
	}

	/**
	 * Test transform_chat with a plain string result.
	 */
	public function test_transform_chat_with_string_result(): void {
		$result = $this->transformer->transform_chat( 'Hello world', 'gpt-4o', 'req-001' );

		$this->assertSame( 'Hello world', $result['choices'][0]['message']['content'] );
		$this->assertSame( 'assistant', $result['choices'][0]['message']['role'] );
	}

	/**
	 * Test transform_chat with array content format.
	 */
	public function test_transform_chat_with_array_content(): void {
		$input  = [ 'content' => 'Array content text' ];
		$result = $this->transformer->transform_chat( $input, 'gpt-4o', 'req-002' );

		$this->assertSame( 'Array content text', $result['choices'][0]['message']['content'] );
	}

	/**
	 * Test transform_chat with nested choices format.
	 */
	public function test_transform_chat_with_choices_format(): void {
		$input = [
			'choices' => [
				[
					'message' => [
						'content' => 'Choices content text',
					],
				],
			],
		];

		$result = $this->transformer->transform_chat( $input, 'gpt-4o', 'req-003' );

		$this->assertSame( 'Choices content text', $result['choices'][0]['message']['content'] );
	}

	/**
	 * Test transform_chat output structure has all required keys.
	 */
	public function test_transform_chat_structure(): void {
		$result = $this->transformer->transform_chat( 'test', 'gpt-4o', 'req-004' );

		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'object', $result );
		$this->assertArrayHasKey( 'created', $result );
		$this->assertArrayHasKey( 'model', $result );
		$this->assertArrayHasKey( 'choices', $result );
		$this->assertArrayHasKey( 'usage', $result );

		$this->assertSame( 'wpmind-req-004', $result['id'] );
		$this->assertSame( 'chat.completion', $result['object'] );
		$this->assertSame( 'gpt-4o', $result['model'] );
		$this->assertSame( 'stop', $result['choices'][0]['finish_reason'] );
		$this->assertSame( 0, $result['choices'][0]['index'] );
	}

	/**
	 * Test transform_chat extracts usage data from result.
	 */
	public function test_transform_chat_usage_extraction(): void {
		$input = [
			'content' => 'text',
			'usage'   => [
				'prompt_tokens'     => 10,
				'completion_tokens' => 20,
				'total_tokens'      => 30,
			],
		];

		$result = $this->transformer->transform_chat( $input, 'gpt-4o', 'req-005' );

		$this->assertSame( 10, $result['usage']['prompt_tokens'] );
		$this->assertSame( 20, $result['usage']['completion_tokens'] );
		$this->assertSame( 30, $result['usage']['total_tokens'] );
	}

	/**
	 * Test transform_embedding output structure.
	 */
	public function test_transform_embedding_structure(): void {
		$input  = [ [ 0.1, 0.2, 0.3 ] ];
		$result = $this->transformer->transform_embedding( $input, 'text-embedding-ada-002', 'req-006' );

		$this->assertSame( 'list', $result['object'] );
		$this->assertCount( 1, $result['data'] );
		$this->assertSame( 'embedding', $result['data'][0]['object'] );
		$this->assertSame( 0, $result['data'][0]['index'] );
		$this->assertSame( [ 0.1, 0.2, 0.3 ], $result['data'][0]['embedding'] );
	}

	/**
	 * Test transform_models output structure.
	 */
	public function test_transform_models_structure(): void {
		$result = $this->transformer->transform_models( [ 'gpt-4o', 'deepseek-chat' ] );

		$this->assertSame( 'list', $result['object'] );
		$this->assertCount( 2, $result['data'] );
		$this->assertSame( 'gpt-4o', $result['data'][0]['id'] );
		$this->assertSame( 'model', $result['data'][0]['object'] );
	}

	/**
	 * Test transform_models sets owned_by to wpmind.
	 */
	public function test_transform_models_owned_by_wpmind(): void {
		$result = $this->transformer->transform_models( [ 'gpt-4o', 'deepseek-chat', 'auto' ] );

		foreach ( $result['data'] as $model ) {
			$this->assertSame( 'wpmind', $model['owned_by'], "Model {$model['id']} should be owned by wpmind" );
		}
	}
}
