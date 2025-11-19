# AI SEO Auditor - WordPress AI SEO审计工具插件

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)

使用Claude AI API自动分析WordPress文章的SEO质量，并提供智能优化建议的专业插件。

## 🌟 主要特性

### SEO全面分析
- ✅ **标题优化** - 长度检查、关键词位置分析、吸引力评分
- ✅ **Meta描述** - 长度验证、关键词包含、CTA检查
- ✅ **关键词使用** - 密度计算、分布分析
- ✅ **内容质量** - 字数统计、标题结构、段落长度
- ✅ **可读性评分** - 句子长度、词汇难度评估
- ✅ **技术SEO** - URL结构、图片优化检查

### 智能AI建议
- 📝 自动生成3-5个优化后的标题建议
- 📝 提供Meta描述优化方案
- 📝 给出具体的内容改进建议
- 📝 智能内链推荐

### 强大的用户界面
- 🎨 文章编辑页面集成Meta Box
- 🎨 独立的设置和批量审计页面
- 🎨 WordPress仪表盘统计Widget
- 🎨 直观的评分卡片展示（0-100分）
- 🎨 一键应用AI建议

### 批量处理能力
- ⚡ 支持批量审计文章
- ⚡ 按分类/标签筛选
- ⚡ CSV报告导出
- ⚡ 实时进度显示

### 竞品分析功能 🆕
- 🔍 输入竞品URL进行SEO分析
- 📊 自动抓取和解析竞品页面
- 📈 与自己的文章进行对比分析
- 💡 识别优势和劣势
- 🎯 提供可学习的优化方向

### 定时任务和自动化 🆕
- ⏰ WP-Cron定时审计
- 📧 每周SEO报告邮件
- 🔄 自动审计低分文章
- 📨 审计完成邮件通知
- 📅 灵活的调度配置

### 图片SEO优化 🆕
- 🖼️ 自动检查图片alt标签
- 📏 图片尺寸和大小分析
- 🎨 图片格式优化建议
- ⚡ 识别过大图片
- 📊 图片SEO评分

### 社交媒体优化 🆕
- 📱 Open Graph标签检查
- 🐦 Twitter Card标签检查
- 🖼️ 社交媒体图片尺寸验证
- 📝 标题和描述优化建议
- 👁️ 社交媒体预览生成

## 📋 系统要求

- WordPress 6.0 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.6 或更高版本
- Claude API Key（从 [Anthropic Console](https://console.anthropic.com/) 获取）

## 🚀 安装步骤

### 方法一：手动安装

1. 下载插件压缩包
2. 登录WordPress后台
3. 进入 `插件` > `安装插件` > `上传插件`
4. 选择下载的压缩包并上传
5. 点击 `立即安装`
6. 安装完成后，点击 `启用插件`

### 方法二：FTP上传

1. 解压插件压缩包
2. 通过FTP将 `ai-seo-auditor` 文件夹上传到 `/wp-content/plugins/` 目录
3. 登录WordPress后台
4. 进入 `插件` 页面
5. 找到 "AI SEO Auditor" 并点击 `启用`

## ⚙️ 配置指南

### 1. 获取Claude API Key

1. 访问 [Anthropic Console](https://console.anthropic.com/)
2. 注册或登录账号
3. 进入 API Keys 页面
4. 创建新的API Key
5. 复制API Key（格式：`sk-ant-api...`）

### 2. 插件配置

1. 在WordPress后台，进入 `AI SEO Auditor` > `设置`
2. 在 "Claude API Key" 字段中粘贴您的API Key
3. 点击 `测试API连接` 验证配置
4. 根据需要调整审计规则：
   - 关键词密度范围（建议1-3%）
   - 标题长度范围（建议50-60字符）
   - Meta描述长度（建议150-160字符）
   - 最小文章字数（建议300字）
   - 分析语言（中文/英文/双语）
5. 点击 `保存设置`

## 📖 使用教程

### 单篇文章审计

1. 编辑任意文章
2. 在文章编辑器下方找到 "AI SEO 审计" Meta Box
3. （可选）设置目标关键词
4. 点击 `开始 SEO 审计` 按钮
5. 等待分析完成（通常需要10-30秒）
6. 查看分析结果和建议：
   - 总体SEO评分
   - 各项详细评分卡片
   - 具体问题和优化建议
7. 应用AI建议：
   - 选择推荐的标题，点击 `应用选中的标题`
   - 选择推荐的Meta描述，点击 `应用选中的描述`
8. 根据其他建议手动优化内容
9. 保存文章

### 批量审计

1. 进入 `AI SEO Auditor` > `批量审计`
2. 选择筛选方式：
   - 所有已发布文章
   - 按分类筛选
   - 按标签筛选
   - 仅未审计的文章
   - 评分低于60的文章
3. 设置审计数量（最多100篇）
4. 点击 `开始批量审计`
5. 等待批量处理完成
6. 查看统计结果
7. （可选）导出CSV报告

### 竞品分析 🆕

1. 进入 `AI SEO Auditor` > `竞品分析`
2. 输入竞品文章的URL
3. （可选）选择自己的文章进行对比
4. 点击 `开始分析`
5. 等待抓取和分析完成
6. 查看分析结果：
   - 竞品的SEO评分
   - 竞品的优势和弱点
   - 可以学习的地方
   - 优化建议
7. 如果选择了对比文章，还会显示：
   - 总分和各维度对比
   - 您的优势领域
   - 需要改进的地方

### 定时任务设置 🆕

1. 进入 `AI SEO Auditor` > `定时任务`
2. 配置定时审计：
   - 启用/禁用自动审计
   - 选择审计频率（每天/每天两次/每周）
   - 设置每次审计文章数量
   - 设置评分阈值（优先审计低分文章）
3. 配置邮件通知：
   - 启用审计完成邮件通知
   - 启用每周SEO报告
4. 点击 `保存设置`
5. 查看任务状态和历史记录

### 查看统计数据

#### 仪表盘Widget

WordPress仪表盘会显示：
- 网站平均SEO评分
- 已审计文章总数
- 需要优化的文章数量
- Top 5需要优化的文章列表

#### 设置页面统计

在设置页面底部可以看到：
- 详细的统计信息
- 需要优化的文章列表

## 🎯 SEO评分标准

### 评分等级

| 分数 | 等级 | 说明 |
|------|------|------|
| 80-100 | 优秀 🟢 | SEO优化良好，继续保持 |
| 60-79 | 良好 🟡 | 基本达标，有提升空间 |
| 40-59 | 需要改进 🟠 | 存在明显问题，建议优化 |
| 0-39 | 急需优化 🔴 | SEO质量较差，急需改进 |

### 各项评分说明

#### 标题优化 (Title)
- 长度是否在50-60字符范围
- 关键词是否前置
- 是否包含数字、情感词等吸引元素

#### Meta描述 (Meta Description)
- 长度是否在150-160字符范围
- 是否包含目标关键词
- 是否有明确的Call-to-Action

#### 关键词使用 (Keyword)
- 关键词密度是否在1-3%范围
- 关键词分布是否自然
- 是否存在关键词堆砌

#### 内容质量 (Content)
- 文章字数是否充足（建议至少300字）
- 段落长度是否适中
- H1/H2/H3标题结构是否清晰

#### 可读性 (Readability)
- 句子长度是否适中
- 复杂词汇使用频率
- 被动语态比例
- 可读性等级（1-10分）

#### 技术SEO (Technical)
- URL结构是否合理
- URL是否包含关键词
- 图片是否有alt属性

## 💡 最佳实践

### 1. 发布前审计
在发布新文章前，务必进行SEO审计，确保文章质量。

### 2. 定期批量审计
建议每月进行一次批量审计，找出需要优化的老文章。

### 3. 关注低分文章
优先优化评分低于60的文章，这些文章提升空间最大。

### 4. 应用AI建议
AI生成的标题和Meta描述通常质量很高，可以直接应用或稍作修改。

### 5. 结合人工判断
虽然AI分析很强大，但仍需结合人工判断，特别是涉及行业专业内容时。

### 6. 保持自然
不要过度优化关键词，保持内容的自然和可读性。

## 🔧 高级功能

### 内链建议（开发中）
插件会分析文章主题，自动推荐相关的内部链接，提升网站内链结构。

### 历史记录
每次审计结果都会保存在数据库中，可以追踪SEO优化进度。

### 导出报告
支持导出CSV格式的SEO审计报告，方便进行数据分析。

## 📊 数据存储

插件使用以下WordPress机制存储数据：

### Post Meta
- `_seo_audit_score` - SEO总评分
- `_seo_audit_results` - 完整的分析结果（JSON格式）
- `_seo_audit_timestamp` - 最后分析时间
- `_ai_seo_target_keyword` - 目标关键词

### 自定义数据表
- `wp_ai_seo_audit_history` - 审计历史记录表

## 🔒 安全性

- ✅ 使用WordPress Nonce防止CSRF攻击
- ✅ 所有输入数据经过严格验证和清理
- ✅ API Key加密存储
- ✅ 严格的权限检查
- ✅ 遵循WordPress安全最佳实践

## 🐛 故障排除

### API调用失败
**问题**：显示"API调用失败"错误

**解决方案**：
1. 检查API Key是否正确配置
2. 确认账户有足够的API额度
3. 检查服务器网络连接
4. 查看WordPress调试日志

### 分析速度慢
**问题**：分析需要很长时间

**解决方案**：
1. 检查网络连接速度
2. 长文章可能需要更多时间
3. API服务器可能负载较高，稍后重试

### 结果显示异常
**问题**：分析结果显示不完整

**解决方案**：
1. 刷新页面重新分析
2. 检查浏览器控制台是否有JavaScript错误
3. 清除浏览器缓存

### 批量审计中断
**问题**：批量审计过程中断

**解决方案**：
1. 减少单次审计的文章数量
2. 检查服务器超时设置
3. 分批次进行审计

## 🤝 支持与反馈

- 📧 邮箱支持：support@example.com
- 🐛 问题反馈：[GitHub Issues](https://github.com/hxxy2012/WordPress-AI-SEO-Plugin/issues)
- 📖 文档：[插件文档](https://github.com/hxxy2012/WordPress-AI-SEO-Plugin)

## 📝 更新日志

### 版本 1.0.0 (2024-11-19)
- 🎉 首次发布
- ✅ 完整的SEO分析功能
- ✅ 标题和Meta描述优化建议
- ✅ 批量审计功能
- ✅ 仪表盘统计Widget
- ✅ CSV报告导出
- 🆕 竞品分析功能
- 🆕 WP-Cron定时任务和邮件通知
- 🆕 图片SEO优化检查
- 🆕 社交媒体优化（Open Graph和Twitter Card）
- 🆕 完整的卸载清理脚本
- 🆕 多语言支持文件(.pot)

## 📜 许可证

本插件基于 GPL v2 或更高版本许可证发布。

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 👨‍💻 开发者信息

- **作者**：AI SEO Team
- **GitHub**：[hxxy2012/WordPress-AI-SEO-Plugin](https://github.com/hxxy2012/WordPress-AI-SEO-Plugin)
- **版本**：1.0.0

## 🙏 致谢

- 感谢 Anthropic 提供强大的 Claude AI API
- 感谢 WordPress 社区的支持
- 感谢所有贡献者和测试用户

## 📚 相关资源

- [Anthropic API 文档](https://docs.anthropic.com/)
- [WordPress 插件开发手册](https://developer.wordpress.org/plugins/)
- [SEO 最佳实践指南](https://developers.google.com/search/docs)

---

**让AI帮助您优化WordPress网站的SEO！** 🚀
