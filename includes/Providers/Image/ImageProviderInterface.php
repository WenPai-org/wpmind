<?php
/**
 * 图像生成 Provider 接口
 *
 * @package WPMind
 * @since 2.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * 图像生成 Provider 接口
 */
interface ImageProviderInterface {

	/**
	 * 生成图像
	 *
	 * @param string $prompt 图像生成提示词
	 * @param array  $options 选项（尺寸、风格等）
	 * @return array{success: bool, url?: string, error?: string}
	 */
	public function generate( string $prompt, array $options = [] ): array;

	/**
	 * 测试连接
	 *
	 * @return array{success: bool, message: string}
	 */
	public function testConnection(): array;

	/**
	 * 获取 Provider 标识
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * 获取 Provider 名称
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * 获取支持的模型列表
	 *
	 * @return array
	 */
	public function getModels(): array;
}
