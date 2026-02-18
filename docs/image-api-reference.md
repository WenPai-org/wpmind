# WPMind 图像服务 API 配置文档

> 最后更新：2026-02-01 (已通过 Gemini CLI 核实)
> 
> 本文档记录了 WPMind 支持的 8 个图像生成服务的 API 配置信息。

---

## 1. OpenAI GPT-Image / DALL-E 3 ✅

### 基本信息
| 属性 | 值 |
|-----|-----|
| **服务商** | OpenAI |
| **模型** | `dall-e-3` (推荐 HD 版) |
| **Base URL** | `https://api.openai.com/v1/` |
| **生成端点** | `POST /images/generations` |
| **认证方式** | `Authorization: Bearer sk-...` |

### 重要提示
- **DALL-E 3 将于 2026年5月12日 废弃**，建议迁移到 `gpt-image-1.5`
- 新的 GPT-Image 系列提供更好的文字渲染和指令遵循能力

### 请求参数
```json
{
  "model": "dall-e-3",
  "prompt": "A beautiful sunset over mountains",
  "n": 1,
  "size": "1024x1024",
  "quality": "hd",
  "style": "vivid"
}
```

### 支持的尺寸
- `1024x1024` (正方形)
- `1024x1792` (竖版)
- `1792x1024` (横版)

### API Key 获取
- 官网：https://platform.openai.com/api-keys

---

## 2. Google Imagen 3 ⚠️ 配置已修正

### 基本信息
| 属性 | 值 |
|-----|-----|
| **服务商** | Google |
| **模型** | `imagen-3.0-generate-001` |
| **Base URL** | `https://generativelanguage.googleapis.com/v1beta/` |
| **生成端点** | `POST /models/imagen-3.0-generate-001:predict` |
| **认证方式** | Header `x-goog-api-key: API_KEY` (推荐) 或 URL 参数 `?key=API_KEY` |

### 修正说明
- ❌ 原配置使用 `v1/` 和 `generateContent`，这是文本模型接口
- ✅ 图像生成使用 `v1beta/` 和 `:predict` 端点

### 请求参数
```json
{
  "instances": [
    {
      "prompt": "A futuristic city skyline at sunset"
    }
  ],
  "parameters": {
    "sampleCount": 1
  }
}
```

### 请求头
```
x-goog-api-key: {API_KEY}
Content-Type: application/json
```

### API Key 获取
- Google AI Studio：https://aistudio.google.com/apikey

---

## 3. 腾讯混元图像 3.0 ⚠️ 配置已修正

### 基本信息
| 属性 | 值 |
|-----|-----|
| **服务商** | 腾讯云 |
| **模型** | `hunyuan-image-v3` |
| **官方 API 端点** | `https://hunyuan.tencentcloudapi.com` |
| **OpenAI 兼容端点** | `https://api.hunyuan.cloud.tencent.com/v1/images/generations` (需确认) |
| **认证方式** | TC3-HMAC-SHA256 签名 (SecretId + SecretKey) |

### 修正说明
- ❌ 原配置 `hunyuan.cloud.tencent.com/hyllm/v1/` 是**文本模型**接口
- ✅ 图像生成使用 Tencent Cloud API 3.0 标准接口

### 官方 API 使用方式
```bash
# Action: SubmitHunyuanImageJob
POST https://hunyuan.tencentcloudapi.com
```

### 请求头（TC3 签名）
```
Authorization: TC3-HMAC-SHA256 Credential=...
Content-Type: application/json
X-TC-Action: SubmitHunyuanImageJob
X-TC-Version: 2023-09-01
X-TC-Region: ap-guangzhou
```

### 建议
- 优先使用腾讯云官方 SDK
- 或等待 OpenAI 兼容接口正式发布

### API Key 获取
- 腾讯云控制台：https://console.cloud.tencent.com/cam/capi

---

## 4. 字节火山引擎 Doubao Image ⚠️ 配置已修正

### 基本信息
| 属性 | 值 |
|-----|-----|
| **服务商** | 字节跳动 / 火山引擎 |
| **模型** | `doubao-image-v1` 或 Endpoint ID (如 `ep-2025xxxx`) |
| **Base URL** | `https://ark.cn-beijing.volces.com/api/v3/` |
| **生成端点** | `POST /images/generations` |
| **认证方式** | `Authorization: Bearer {API_KEY}` |

### 修正说明
- ✅ 路径需要补充完整：`/api/v3/images/generations`
- ⚠️ 需要在火山方舟创建推理接入点获取 Endpoint ID

### 请求参数
```json
{
  "model": "doubao-image-v1",
  "prompt": "赛博朋克风格的未来城市",
  "size": "1024x1024"
}
```

### 注意事项
- Seedream 4.5 定价：¥0.25/张
- Seedream 4.0 定价：¥0.2/张

### API Key 获取
- 火山引擎控制台：https://console.volcengine.com/ark

---

## 5. Flux (Black Forest Labs via Fal.ai) ⚠️ 认证方式已修正

### 基本信息
| 属性 | 值 |
|-----|-----|
| **服务商** | Black Forest Labs (BFL) |
| **API 提供商** | Fal.ai |
| **模型** | `flux/dev`, `flux/schnell`, `flux-pro-1.1` |
| **Base URL** | `https://fal.run/fal-ai/` |
| **生成端点** | 开发版: `POST /flux/dev`, 极速版: `POST /flux/schnell`, Pro: `POST /flux-pro` |
| **认证方式** | `Authorization: Key {KEY_ID}:{KEY_SECRET}` ⚠️ 注意是 `Key` 不是 `Bearer` |

### 修正说明
- ❌ 原配置使用 `Bearer` 认证
- ✅ Fal.ai 使用 `Key` 认证格式

### 请求参数
```json
{
  "prompt": "A photorealistic portrait",
  "image_size": {
    "width": 1024,
    "height": 1024
  },
  "num_images": 1
}
```

### FLUX.2 新功能 (2026年1月)
- **FLUX.2 [klein]**：4B/9B 参数版本，亚秒级推理
- 支持多参考图像工作流

### API Key 获取
- Fal.ai：https://fal.ai/dashboard/keys

---

## 6. 通义万相 (Aliyun Wanxiang) ✅

### 基本信息
| 属性 | 值 |
|-----|-----|
| **服务商** | 阿里云 |
| **模型** | `wanx-v1`, `wanx-background-generation-v2` |
| **Base URL** | `https://dashscope.aliyuncs.com/api/v1/` |
| **生成端点** | `POST /services/aigc/text2image/image-synthesis` |
| **认证方式** | `Authorization: Bearer {API_KEY}` |

### 异步任务模式
通义万相使用异步任务模式：
1. 提交任务 → 获取 `task_id`
2. 轮询任务状态 → 获取结果

### 请求参数
```json
{
  "model": "wanx-v1",
  "input": {
    "prompt": "一只可爱的熊猫在竹林中"
  },
  "parameters": {
    "size": "1024*1024",
    "n": 1
  }
}
```

### 请求头
```
Authorization: Bearer {API_KEY}
X-DashScope-Async: enable
Content-Type: application/json
```

### 注意
- 如果使用了工作空间，需要添加 `X-DashScope-WorkSpace` 头

### API Key 获取
- 阿里云 DashScope：https://dashscope.console.aliyun.com/apiKey

---

## 核实后的汇总对比表

| 服务商 | 端点路径 | 认证方式 | 核心模型 | 状态 |
|-------|---------|---------|---------|------|
| **OpenAI** | `/v1/images/generations` | `Bearer sk-...` | `dall-e-3` | ✅ 已验证 |
| **Google Imagen** | `/models/imagen-3.0-generate-001:predict` | `x-goog-api-key: ...` | `imagen-3.0-generate-001` | ⚠️ 已修正 |
| **腾讯混元** | Tencent Cloud API 3.0 | TC3-HMAC-SHA256 | `hunyuan-image-v3` | ⚠️ 需 SDK |
| **火山引擎** | `/api/v3/images/generations` | `Bearer ...` | `doubao-image-v1` | ⚠️ 需 Endpoint ID |
| **Fal.ai Flux** | `/fal-ai/flux/dev` | `Key ID:SECRET` | `flux/dev` | ⚠️ 认证已修正 |
| **通义万相** | `/services/aigc/text2image/image-synthesis` | `Bearer ...` | `wanx-v1` | ✅ 已验证 |

---

## 配置建议

### 中国用户推荐
1. **通义万相** - 配置简单、开源、无 AI 味
2. **火山引擎 Doubao** - 性价比高、OpenAI 兼容
3. **腾讯混元** - 中文理解强，但需要 SDK

### 国际用户推荐
1. **OpenAI DALL-E 3** - 综合最均衡
2. **Fal.ai Flux** - 开源天花板（注意认证方式）
3. **Google Imagen 3** - 文字渲染最稳

---

*本文档由 WPMind 生成，已通过 Gemini CLI 于 2026-02-01 核实。*
