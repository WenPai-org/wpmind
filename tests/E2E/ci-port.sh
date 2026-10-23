#!/usr/bin/env bash
# 给 e2e 占一个空闲端口，避免同一台 runner 上并发的 e2e job 抢同一个端口。
#
# 两个 job 同时用固定端口时，后启动的 wp-env start 失败（端口被占），它的 Playwright 却连上
# 前一个 job 的站点，两边互相复位/改选项，用例随机失败（run 352、357 就是这样重叠的）。
#
# 用法：
#   tests/E2E/ci-port.sh claim     在 [E2E_PORT_FIRST, E2E_PORT_LAST] 里占一个端口，输出端口号
#   tests/E2E/ci-port.sh release   释放 WPMIND_E2E_PORT 的占用
# 占用用 mkdir 加锁（原子操作）；锁目录 E2E_PORT_LOCK_DIR，默认在系统临时目录。
# 超过 E2E_PORT_STALE_MIN 分钟（默认 120）且端口空闲的锁视为被取消的 job 留下的，回收。
set -euo pipefail

first=${E2E_PORT_FIRST:-8946}
last=${E2E_PORT_LAST:-8949}
lock_dir=${E2E_PORT_LOCK_DIR:-${TMPDIR:-/tmp}/wpmind-e2e-ports}
stale_min=${E2E_PORT_STALE_MIN:-120}

port_in_use() {
	(exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null
}

case "${1:-}" in
claim)
	mkdir -p "${lock_dir}"
	for attempt in $(seq 1 60); do
		for port in $(seq "${first}" "${last}"); do
			lock="${lock_dir}/${port}"
			if [ -d "${lock}" ] && [ -n "$(find "${lock}" -maxdepth 0 -mmin +"${stale_min}")" ] && ! port_in_use "${port}"; then
				rmdir "${lock}" 2>/dev/null || true
			fi
			if mkdir "${lock}" 2>/dev/null; then
				if port_in_use "${port}"; then
					rmdir "${lock}"
					continue
				fi
				echo "${port}"
				exit 0
			fi
		done
		echo "端口 ${first}-${last} 都被占用，10 秒后重试（${attempt}/60）" >&2
		sleep 10
	done
	echo "等了 10 分钟仍没有空闲端口" >&2
	exit 1
	;;
release)
	if [ -n "${WPMIND_E2E_PORT:-}" ]; then
		rmdir "${lock_dir}/${WPMIND_E2E_PORT}" 2>/dev/null || true
	fi
	;;
*)
	echo "usage: $0 claim|release" >&2
	exit 2
	;;
esac
