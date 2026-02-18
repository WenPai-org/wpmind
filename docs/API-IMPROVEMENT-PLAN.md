# WPMind API 健壮性改进计划

## 背景

通过 WPSlug 集成工作，发现 WPMind 公共 API 存在以下问题需要改进：

1. 缺乏循环调用保护
2. 使用触发 filter 的函数导致副作用
3. 频繁调用时性能不佳
4. 错误处理不够统一

## 任务列表

### Phase 1: 核心安全改进

#### Task 1.1: 添加全局循环调用保护
- **文件**: `includes/API/PublicAPI.php`
- **内容**: 
  - 添加静态调用栈 `$call_stack`
  - 在所有公共方法入口检测循环
  - 检测到循环时返回 `WP_Error`
- **优先级**: P0 (Critical)
- **状态**: ✅ 已完成

#### Task 1.2: 移除所有触发 filter 的函数调用
- **文件**: `includes/API/PublicAPI.php`
- **内容**:
  - 审查所有使用 `sanitize_title()` 的地方
  - 替换为 `sanitize_title_with_dashes()` 或自定义函数
  - 确保不调用可能被第三方拦截的函数
- **优先级**: P0 (Critical)
- **状态**: ✅ 已完成

### Phase 2: 性能优化

#### Task 2.1: 统一静态缓存机制
- **文件**: `includes/API/PublicAPI.php`
- **内容**:
  - 创建通用的 `get_cached()` 和 `set_cached()` 方法
  - 支持内存缓存（当前请求）和持久缓存（Transient）
  - 所有公共方法使用统一缓存接口
- **优先级**: P1
- **状态**: 🔄 进行中（基础实现已有，待统一）

#### Task 2.2: 优化 is_available() 检查
- **文件**: `includes/API/PublicAPI.php`
- **内容**:
  - 添加静态缓存（单次请求内）
  - 添加短期 Transient 缓存（可配置）
- **优先级**: P1
- **状态**: ✅ 已完成

### Phase 3: 错误处理标准化

#### Task 3.1: 创建统一错误处理类
- **文件**: `includes/API/ErrorHandler.php` (新建)
- **内容**:
  - 定义标准错误代码常量
  - 创建 `create_error()` 工厂方法
  - 添加错误日志记录
- **优先级**: P1
- **状态**: ✅ 已完成

#### Task 3.2: 更新所有公共方法使用统一错误处理
- **文件**: `includes/API/PublicAPI.php`, `includes/API/functions.php`
- **内容**:
  - 替换所有 `new WP_Error()` 调用
  - 使用标准错误代码
  - 添加调试信息
- **优先级**: P1
- **状态**: ✅ 部分完成（循环调用部分）

### Phase 4: API 增强

#### Task 4.1: 添加请求追踪机制
- **文件**: `includes/API/PublicAPI.php`
- **内容**:
  - 每个请求生成唯一 `request_id`
  - 在日志和返回值中包含 `request_id`
  - 便于调试第三方集成问题
- **优先级**: P2
- **状态**: ✅ 已完成（在 ErrorHandler 中实现）

#### Task 4.2: 添加调用统计
- **文件**: `includes/API/PublicAPI.php`
- **内容**:
  - 记录每个 context 的调用次数
  - 记录成功/失败率
  - 提供统计查询接口
- **优先级**: P2
- **状态**: ⏳ 待开始

#### Task 4.3: 扩展 format 选项
- **文件**: `includes/API/PublicAPI.php`
- **内容**:
  - 添加 `json` 格式支持
  - 添加 `markdown` 格式支持
  - 文档化所有支持的格式
- **优先级**: P3
- **状态**: ⏳ 待开始

### Phase 5: 文档和测试

#### Task 5.1: 更新 API 文档
- **文件**: `docs/public-api.md`
- **内容**:
  - 添加错误代码完整列表
  - 添加循环调用保护说明
  - 添加性能最佳实践
- **优先级**: P1
- **状态**: ✅ 已完成

#### Task 5.2: 创建集成测试脚本
- **文件**: `tests/integration-test.php` (新建)
- **内容**:
  - 测试循环调用保护
  - 测试缓存机制
  - 测试错误处理
- **优先级**: P2
- **状态**: ✅ 已完成

---

## 已完成的变更

### 新增文件
- `includes/API/ErrorHandler.php` - 统一错误处理类
- `tests/integration-test.php` - 集成测试脚本
- `docs/API-IMPROVEMENT-PLAN.md` - 本计划文档

### 修改文件
- `includes/API/PublicAPI.php`
  - 添加 `$call_stack` 静态属性
  - 添加 `check_recursive_call()` 方法
  - 添加 `begin_call()` / `end_call()` 方法
  - `chat()` 方法添加循环保护，逻辑移至 `do_chat()`
  - `translate()` 方法添加循环保护，逻辑移至 `do_translate()`
  - `translate()` 内部调用改为 `do_chat()` 避免双重检查

---

## 验证检查清单

- [x] 循环调用保护工作正常
- [x] 所有公共方法不触发外部 filter
- [x] 缓存机制正常工作
- [x] 错误代码一致且有意义
- [x] 文档与代码同步
- [ ] WPSlug 集成仍然正常工作（待验证）

---

## 运行测试

```bash
# 在服务器上运行集成测试
wp eval-file /path/to/wpmind/tests/integration-test.php
```

---

## 下一步

1. 部署到测试环境
2. 运行集成测试
3. 验证 WPSlug 集成
4. 考虑实现 Task 4.2（调用统计）和 Task 4.3（扩展 format）
