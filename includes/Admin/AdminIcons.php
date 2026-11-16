<?php
/**
 * 后台内联 SVG 图标（stroke 风格，24x24 viewBox）。
 * kit 的 Wenpai_Admin_Icons 只有通用语义图标；控制台导航等产品图标走这里。
 *
 * @package WPMind\Admin
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Admin;

/**
 * Class AdminIcons
 */
final class AdminIcons {

	/**
	 * 图标 path 数据（stroke 图标：只写内部图形，属性由 svg() 统一加）。
	 *
	 * @var array<string, string>
	 */
	private const PATHS = [
		// 概览：四宫格仪表盘
		'overview' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
		// AI 服务：机器人头
		'services' => '<rect x="4" y="8" width="16" height="12" rx="2"/><path d="M12 8V4"/><circle cx="12" cy="3" r="1"/><circle cx="9" cy="13" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="13" r="1" fill="currentColor" stroke="none"/><path d="M9 17h6"/>',
		// 模块：2x2 方块阵
		'modules'  => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
		// 分析：柱状图
		'analytics' => '<path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6" rx="0.5"/><rect x="12" y="8" width="3" height="10" rx="0.5"/><rect x="17" y="5" width="3" height="13" rx="0.5"/>',
		// 任务：清单
		'workflow' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="m3.5 5.5 1.2 1.2L7 4.5M3.5 11.5l1.2 1.2L7 10.5M3.5 17.5l1.2 1.2L7 16.5"/>',
		// 工具：扳手
		'tools'    => '<path d="M14.7 6.3a4.5 4.5 0 0 0-6 5.6L3 17.6V21h3.4l5.7-5.7a4.5 4.5 0 0 0 5.6-6l-3 3-2.5-.5-.5-2.5 3-3Z"/>',
		// 帮助：问号圆
		'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 0 1 4.9.7c0 1.6-2.4 2-2.4 3.3"/><circle cx="12" cy="17" r="0.5" fill="currentColor" stroke="none"/>',
		// 反馈：气泡
		'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r="0.5" fill="currentColor" stroke="none"/>',

		'feedback' => '<path d="M21 12a8 8 0 0 1-8 8H4l2-3.2A8 8 0 1 1 21 12Z"/><path d="M8.5 11h.01M12 11h.01M15.5 11h.01"/>',
		// 锁：核心模块
		'lock'     => '<rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
		// 外链
		'external' => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
		// 下载/导出
		'download' => '<path d="M12 4v11"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/>',
		// 上传/导入
		'upload'   => '<path d="M12 15V4"/><path d="m7 8 5-5 5 5"/><path d="M5 20h14"/>',
		// 刷新
		'refresh'  => '<path d="M21 12a9 9 0 1 1-2.6-6.3"/><path d="M21 3v6h-6"/>',
		// 文档
		'doc'      => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/>',
		// 警告
		'alert'    => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4"/><circle cx="12" cy="17" r="0.5" fill="currentColor" stroke="none"/>',
		// 费用/预算：钱币
		'wallet'   => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M16 6V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v1"/><circle cx="16.5" cy="12.5" r="1" fill="currentColor" stroke="none"/>',
		// 地球/GEO
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		// 图片/媒体
		'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 18 5-5 3 3 4-4 4 4"/>',
		// 闪电/缓存加速
		'zap'      => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>',
		// 钥匙/网关凭证
		'key'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 7l3 3M13.5 9.5l2.5 2.5"/>',
		// 机器人（备用）
		'robot'    => '<rect x="5" y="8" width="14" height="11" rx="2"/><path d="M12 8V4M9 4h6"/><circle cx="9.5" cy="13" r="1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="13" r="1" fill="currentColor" stroke="none"/><path d="M9.5 16.5h5"/>',
		// 路由/分流
		'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
		'eye-off'   => '<path d="M3 3l18 18"/><path d="M10.6 6.1A9.8 9.8 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3.2 3.9M6.6 6.6C3.9 8.4 2 12 2 12s3.5 6 10 6c1.5 0 2.9-.3 4.1-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
		'route'    => '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="9" r="2.5"/><path d="M6 8.5v7M18 11.5c0 4-4 4.5-8 5"/>',
	];

	/**
	 * 取图标 SVG 字符串。
	 *
	 * @param string $name   PATHS 键名。
	 * @param string $class  额外 class（默认 wenpai-ico）。
	 * @return string
	 */
	public static function svg( string $name, string $class = 'wenpai-ico' ): string {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
			esc_attr( $class ),
			self::PATHS[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 静态 path 数据。
		);
	}
}
