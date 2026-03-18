<?php
/**
 * Image Service
 *
 * 处理图像生成
 *
 * @package WPMind
 * @subpackage API\Services
 * @since 3.7.0
 */

declare(strict_types=1);

namespace WPMind\API\Services;

use WP_Error;

/**
 * Image Service
 *
 * @since 3.7.0
 */
class ImageService extends AbstractService {

	/**
	 * 生成图像
	 *
	 * @param string $prompt  图像描述
	 * @param array  $options 选项
	 * @return array|WP_Error
	 */
	public function generate_image( string $prompt, array $options = [] ) {
		$defaults = [
			'context'       => 'image_generation',
			'size'          => '1024x1024',
			'quality'       => 'standard',
			'style'         => 'natural',
			'provider'      => 'auto',
			'return_format' => 'url',
		];
		$options  = wp_parse_args( $options, $defaults );

		$context = $options['context'];

		do_action( 'wpmind_before_request', 'image', compact( 'prompt', 'options' ), $context );

		if ( class_exists( '\\WPMind\\Providers\\Image\\ImageRouter' ) ) {
			$router = \WPMind\Providers\Image\ImageRouter::instance();
			$result = $router->generate( $prompt, $options );
		} else {
			return new WP_Error(
				'wpmind_image_not_available',
				__( '图像生成服务不可用', 'wpmind' )
			);
		}

		if ( is_wp_error( $result ) ) {
			do_action( 'wpmind_error', $result, 'image', compact( 'prompt', 'options' ) );
			return $result;
		}

		do_action( 'wpmind_after_request', 'image', $result, compact( 'prompt', 'options' ), [] );

		return $result;
	}
}
