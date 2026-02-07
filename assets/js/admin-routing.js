/**
 * WPMind Admin routing panel handlers.
 *
 * @package WPMind
 * @since 3.3.0
 */

(function ($) {
	'use strict';

	var Admin = window.WPMindAdmin || (window.WPMindAdmin = {});
	var Toast = Admin.Toast || {
		success: function () { },
		error: function () { },
		warning: function () { },
		info: function () { }
	};
	var Dialog = Admin.Dialog || {
		show: function () { }
	};

	/**
	 * Routing Panel 路由管理
	 */
	var RoutingManager = {
		init: function () {
			if (!$('.wpmind-routing-panel').length) return;
			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			// 策略选择 - 监听 radio change 事件
			$(document).on('change', 'input[name="routing_strategy"]', function () {
				var strategy = $(this).val();
				self.setStrategy(strategy);
			});

			// 策略卡片点击 - 备用方案
			$(document).on('click', '.wpmind-strategy-item', function () {
				var $radio = $(this).find('input[type="radio"]');
				if (!$radio.prop('checked')) {
					$radio.prop('checked', true).trigger('change');
				}
			});

			// 刷新路由状态
			$(document).on('click', '.wpmind-refresh-routing', function (e) {
				e.preventDefault();
				var $btn = $(this);
				$btn.find('.dashicons').addClass('wpmind-spinning');
				self.refreshStatus(function () {
					$btn.find('.dashicons').removeClass('wpmind-spinning');
				});
			});

			// 初始化拖拽排序
			self.initSortable();

			// 保存优先级
			$(document).on('click', '.wpmind-save-priority', function (e) {
				e.preventDefault();
				self.savePriority();
			});

			// 清除优先级
			$(document).on('click', '.wpmind-clear-priority', function (e) {
				e.preventDefault();
				self.clearPriority();
			});
		},

		initSortable: function () {
			var $list = $('#wpmind-priority-list');
			if (!$list.length) return;

			// 检查 jQuery UI sortable 是否可用
			if (typeof $.fn.sortable !== 'function') {
				console.warn('WPMind: jQuery UI Sortable not available');
				return;
			}

			$list.sortable({
				handle: '.wpmind-priority-handle',
				placeholder: 'wpmind-priority-placeholder',
				axis: 'y',
				tolerance: 'pointer',
				update: function () {
					// 更新序号显示
					$list.find('.wpmind-priority-item').each(function (index) {
						$(this).find('.wpmind-priority-index').text(index + 1);
					});
				}
			});
		},

		savePriority: function () {
			var self = this;
			if (typeof wpmindData === 'undefined') {
				Toast.error('配置错误');
				return;
			}

			var $list = $('#wpmind-priority-list');
			var priority = [];
			$list.find('.wpmind-priority-item').each(function () {
				priority.push($(this).data('provider'));
			});

			if (priority.length === 0) {
				Toast.warning('没有可排序的 Provider');
				return;
			}

			var $btn = $('.wpmind-save-priority');
			$btn.prop('disabled', true).find('.dashicons').addClass('wpmind-spinning');

			$.ajax({
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_set_provider_priority',
					priority: priority,
					nonce: wpmindData.nonce
				},
				success: function (response) {
					if (response.success) {
						Toast.success('优先级已保存');
						// 显示清除按钮
						if (!$('.wpmind-clear-priority').length) {
							$('.wpmind-routing-priority-actions').prepend(
								'<button type="button" class="button button-small wpmind-clear-priority" title="清除手动优先级">' +
								'<span class="dashicons ri-delete-bin-line"></span> 清除</button>'
							);
						}
						// 显示已启用标记
						if (!$('.wpmind-priority-badge').length) {
							$('.wpmind-routing-priority .wpmind-routing-section-desc').append(
								' <span class="wpmind-priority-badge">已启用手动优先级</span>'
							);
						}
						// 刷新路由状态
						self.refreshStatus();
					} else {
						var msg = (response.data && response.data.message) || '保存失败';
						Toast.error(msg);
					}
				},
				error: function () {
					Toast.error('保存失败，请重试');
				},
				complete: function () {
					$btn.prop('disabled', false).find('.dashicons').removeClass('wpmind-spinning');
				}
			});
		},

		clearPriority: function () {
			var self = this;
			if (typeof wpmindData === 'undefined') {
				Toast.error('配置错误');
				return;
			}

			Dialog.show({
				title: '清除手动优先级',
				message: '确定要清除手动优先级设置吗？<br><small style="color:#666;">清除后将使用智能路由自动排序</small>',
				type: 'warning',
				confirmText: '确定清除',
				cancelText: '取消',
				onConfirm: function () {
					var $btn = $('.wpmind-clear-priority');
					$btn.prop('disabled', true).find('.dashicons').addClass('wpmind-spinning');

					$.ajax({
						url: wpmindData.ajaxurl || ajaxurl,
						type: 'POST',
						data: {
							action: 'wpmind_set_provider_priority',
							priority: [],
							clear: 1,
							nonce: wpmindData.nonce
						},
						success: function (response) {
							if (response.success) {
								Toast.success('手动优先级已清除');
								// 移除清除按钮和标记
								$('.wpmind-clear-priority').remove();
								$('.wpmind-priority-badge').remove();
								// 刷新路由状态
								self.refreshStatus();
							} else {
								var msg = (response.data && response.data.message) || '清除失败';
								Toast.error(msg);
							}
						},
						error: function () {
							Toast.error('清除失败，请重试');
						},
						complete: function () {
							$btn.prop('disabled', false).find('.dashicons').removeClass('wpmind-spinning');
						}
					});
				}
			});
		},

		setStrategy: function (strategy) {
			if (typeof wpmindData === 'undefined') {
				Toast.error('配置错误');
				return;
			}

			// 更新 UI 状态
			$('.wpmind-strategy-item').removeClass('is-active');
			$('input[name="routing_strategy"][value="' + strategy + '"]')
				.closest('.wpmind-strategy-item')
				.addClass('is-active');

			$.ajax({
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_set_routing_strategy',
					strategy: strategy,
					nonce: wpmindData.nonce
				},
				success: function (response) {
					if (response.success) {
						Toast.success('路由策略已更新');
						// 刷新得分显示
						RoutingManager.refreshStatus();
					} else {
						var msg = (response.data && response.data.message) || '更新失败';
						Toast.error(msg);
					}
				},
				error: function () {
					Toast.error('更新失败，请重试');
				}
			});
		},

		refreshStatus: function (callback) {
			if (typeof wpmindData === 'undefined') {
				if (typeof callback === 'function') callback();
				return;
			}

			$.ajax({
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_get_routing_status',
					nonce: wpmindData.nonce
				},
				success: function (response) {
					if (response.success && response.data) {
						RoutingManager.updateDisplay(response.data);
						Toast.success('路由状态已刷新');
					}
				},
				error: function () {
					Toast.error('刷新失败');
				},
				complete: function () {
					if (typeof callback === 'function') callback();
				}
			});
		},

		updateDisplay: function (data) {
			// 更新得分排名
			var $scores = $('#wpmind-routing-scores');
			if ($scores.length && data.provider_scores) {
				$scores.empty();
				$.each(data.provider_scores, function (providerId, scoreData) {
					var isTop = scoreData.rank === 1;
					var $item = $('<div class="wpmind-routing-score-item"></div>');
					if (isTop) $item.addClass('is-top');
					$item.append($('<span class="wpmind-routing-rank"></span>').text(scoreData.rank));
					$item.append($('<span class="wpmind-routing-provider-name"></span>').text(scoreData.name));
					var $bar = $('<div class="wpmind-routing-score-bar"></div>');
					$bar.append($('<div class="wpmind-routing-score-fill"></div>').css('width', scoreData.score + '%'));
					$item.append($bar);
					$item.append($('<span class="wpmind-routing-score-value"></span>').text(scoreData.score.toFixed(1)));
					$scores.append($item);
				});
			}

			// 更新推荐 Provider
			if (data.recommended && data.provider_scores && data.provider_scores[data.recommended]) {
				var recommendedData = data.provider_scores[data.recommended];
				$('#wpmind-recommended-provider').text(recommendedData.name);
				// 更新得分显示
				$('.wpmind-routing-status-score-value').text(recommendedData.score.toFixed(1));
			}

			// 更新故障转移链 - 新的可视化结构
			var $failoverChain = $('#wpmind-failover-chain');
			if ($failoverChain.length && data.failover_chain && data.failover_chain.length) {
				$failoverChain.empty();
				$.each(data.failover_chain, function (index, provider) {
					var isFirst = index === 0;
					var $node = $('<div class="wpmind-routing-failover-node"></div>');
					if (isFirst) $node.addClass('is-active');
					$node.append('<span class="wpmind-routing-failover-dot"></span>');
					$node.append($('<span class="wpmind-routing-failover-name"></span>').text(provider));
					if (isFirst) {
						$node.append('<span class="wpmind-routing-failover-badge">主</span>');
					}
					$failoverChain.append($node);
					// 添加连接线（除了最后一个）
					if (index < data.failover_chain.length - 1) {
						$failoverChain.append('<div class="wpmind-routing-failover-line"></div>');
					}
				});
			}
		}
	};

	Admin.RoutingManager = RoutingManager;

	/**
	 * Initialize on document ready
	 */
	$(function () {
		if (!$('.wpmind-routing-panel').length) return;

		var safeInit = Admin.safeInit || function (label, fn) {
			try {
				fn();
			} catch (error) {
				console.warn('[WPMind] ' + label + ' init failed:', error);
			}
		};

		safeInit('routing', function () {
			RoutingManager.init();
		});
	});
})(jQuery);
