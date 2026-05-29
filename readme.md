<div align="center">
<img src="https://github.com/xrgzs/sdlp/assets/26499123/1b2af287-6ee9-4795-9404-83b9687d7cf4" alt="XRSOFT_LOGO_ROUND_1024" width="20%" />

# SDLP - 软件下载链接解析器

一个用于收集不同软件直接下载链接解析方式的后端项目，潇然系统的子项目之一 🌟🚀

[![Docker Image](https://img.shields.io/badge/Docker-ghcr.io%2Fxrgzs%2Fsdlp-blue?logo=docker)](https://ghcr.io/xrgzs/sdlp)
[![PHP Version](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

</div>

## ✨ 特性

- 🔗 **一键直链**：输入软件 ID 或名称，直接跳转到下载链接
- ⚡ **APCu 缓存**：内置内存缓存支持，减少重复 API 请求
- 🐳 **Docker 部署**：开箱即用，一条命令即可启动
- 🔒 **安全加固**：禁用危险函数，关闭 `expose_php`
- 🌐 **镜像加速**：GitHub Release 支持镜像加速下载

## 📦 接口一览

### 软件直链下载

| 接口 | 说明 | 示例 |
|------|------|------|
| `/soft/huorong` | 火绒安全软件 | [直接访问](/soft/huorong) |
| `/soft/wetype` | 微信输入法 | [直接访问](/soft/wetype) |
| `/soft/baidupinyin` | 百度拼音输入法 | [直接访问](/soft/baidupinyin) |
| `/soft/sunlogin` | 向日葵远程控制 | [查看参数](#向日葵) |
| `/soft/raylink` | RayLink 远程控制 | [直接访问](/soft/raylink) |
| `/soft/asklink` | 连连控 | [直接访问](/soft/asklink) |
| `/soft/ecloud` | 天翼网盘客户端 | [直接访问](/soft/ecloud) |
| `/soft/filecxx/code` | FileCentro 激活码 | [直接访问](/soft/filecxx/code) |

### 软件商店跳转

| 接口 | 说明 | 参数 |
|------|------|------|
| `/360baoku/` | 360 宝库 | `appid` |
| `/lestore/` | 联想软件商店 | `softid` |
| `/qqsoft/` | QQ 软件中心 | `softid` |
| `/qaxsoft/` | 奇安信软件中心 | `softid` |
| `/hpm/` | HotPE 模块 | `name` |
| `/scoop/` | Scoop 包管理器 | `name` `bucket` `branch` `arch` |
| `/ghrelease` | GitHub Release | `repo` `tag` `search` `filter` `mirror` |
| `/lanzou/` | 蓝奏云解析 | `url` `pwd` `type` |
| `/mediafire/` | MediaFire 解析 | `url` |
| `/qfile/` | QQ 文件转发 | `batchId` |

### 随机壁纸

| 接口 | 说明 |
|------|------|
| `/wall/` | 自动切换（随机选择以下接口） |
| `/wall/bingtoday.php` | 必应每日一图 |
| `/wall/bingrand.php` | 必应随机图片 |
| `/wall/itab.php` | iTab 标签页壁纸 |
| `/wall/itabrand.php` | iTab 随机壁纸 |
| `/wall/wetab.php` | WeTab 标签页壁纸 |
| `/wall/wetabrand.php` | WeTab 随机壁纸 |

## 🚀 快速开始

### Docker 部署（推荐）

```bash
# 创建目录并启动
mkdir -p /opt/sdlp && cd /opt/sdlp
curl -fsSL https://raw.githubusercontent.com/xrgzs/sdlp/main/compose.yml -o compose.yml
docker compose up -d
```

访问 `http://your-domain:5080` 即可使用。

> 💡 Docker 镜像已内置 APCu 扩展，缓存功能开箱即用。

### 手动部署

**环境要求：**
- PHP 8.0+
- 扩展：`curl`、`apcu`（可选，用于缓存）

**安装步骤：**

```bash
# 克隆仓库
cd /www/sites/your-domain/index
git clone https://github.com/xrgzs/sdlp.git

# 配置 Nginx
# 运行目录设置为 /sdlp
```

**Nginx 配置示例：**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /www/sites/your-domain/index/sdlp;
    index index.php index.html;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 📖 接口详细说明

### 向日葵

支持多种版本，通过 `name` 和 `x64` 参数控制：

| name | x64 | 说明 |
|------|-----|------|
| `SUNLOGIN_X_WINDOWS` | 否 | 个人版 32 位 |
| `SUNLOGIN_X_WINDOWS` | 是 | 个人版 64 位 |
| `SUNLOGIN_WINDOWS` | 否 | 企业版控制端 32 位 |
| `SUNLOGIN_WINDOWS` | 是 | 企业版控制端 64 位 |
| `SLRC_WINDOWS_ENT` | 否 | 企业版客户端 32 位 |
| `SLRC_WINDOWS_ENT` | 是 | 企业版客户端 64 位 |
| `SL_WINDOWS_LITE` | 否 | SOS 版 32 位 |
| `SL_WINDOWS_LITE` | 是 | SOS 版 64 位 |

```
/soft/sunlogin?name=SUNLOGIN_X_WINDOWS&x64=1
```

### RayLink

默认下载完整版，添加 `?lite` 参数下载精简版：

```
/soft/raylink          # 完整版
/soft/raylink?lite     # Lite 版
```

### 360 宝库

从软件下载页面 URL 中提取 `appid`：

```
页面：http://baoku.360.cn/soft/show/appid/104693057
接口：/360baoku/?appid=104693057
```

### 联想软件商店

从软件详情页 URL 中提取 `softid`：

```
页面：https://lestore.lenovo.com/detail/13407
接口：/lestore/?softid=13407
```

### QQ 软件中心

从软件详情页 URL 中提取 `softid`：

```
页面：https://pc.qq.com/detail/11/detail_351.html
接口：/qqsoft/?softid=351
```

### Scoop 包管理器

| 参数 | 说明 | 默认值 |
|------|------|--------|
| `name` | 软件名称（必填） | - |
| `bucket` | 存储库 | `ScoopInstaller/Main` |
| `branch` | 分支 | `master` |
| `arch` | 架构 | `64bit` |

```
/scoop/?name=aria2
/scoop/?name=ecloud&bucket=xrgzs/sdoog&branch=master
```

### GitHub Release

| 参数 | 说明 | 必填 | 默认值 |
|------|------|------|--------|
| `repo` | 仓库名称 | 是 | - |
| `tag` | 版本号 | 否 | `latest` |
| `search` | 搜索关键词 | 否 | 匹配最后一个文件 |
| `filter` | 二次过滤（文件扩展名） | 否 | - |
| `mirror` | 加速镜像 | 否 | 直连 |

```
/ghrelease?repo=myusername/myrepo
/ghrelease?repo=myusername/myrepo&tag=1.0.0&search=win_x64
/ghrelease?repo=myusername/myrepo&search=win_x64&filter=.exe&mirror=auto
```

### 蓝奏云

| 参数 | 说明 | 必填 |
|------|------|------|
| `url` | 蓝奏云链接 | 是 |
| `pwd` | 提取密码 | 否 |
| `type` | 设为 `down` 直接下载 | 否 |

```
# 直接下载
/lanzou/?url=https://www.lanzoup.com/iNfv31pt6oab&type=down

# 带密码
/lanzou/?url=https://www.lanzoum.com/ixkVm66d3kd&pwd=2333

# 输出 JSON（含直链）
/lanzou/?url=https://www.lanzoup.com/iNfv31pt6oab
```

### 奇安信软件中心

从软件详情页中提取 `softid`：

```
/qaxsoft/?softid=103352
```

### HPM 模块

根据名称前缀搜索 HotPE 模块，自动匹配最新版本：

```
/hpm/?name=ToDesk完整版
/hpm/?name=7-Zip
```

### MediaFire

解析 MediaFire 文件直链：

```
/mediafire/?url=https://www.mediafire.com/file/xxxxx/file.zip
```

### QQ 文件转发

解析 QQ 文件转发直链，需要 `batchId`：

```
/qfile/?batchId=xxxxxx
```

## ⚡ 缓存机制

项目使用 APCu 内存缓存，减少对外部 API 的重复请求。

### 缓存策略

| 接口类型 | 缓存时间 | 说明 |
|----------|----------|------|
| 软件直链 | 10 分钟 | 下载链接相对稳定 |
| 软件商店 | 10 分钟 | 应用版本更新不频繁 |
| GitHub Release | 10 分钟 | 避免触发 API 限制 |
| 蓝奏云 | 10 分钟 | 文件链接相对稳定 |
| 壁纸（必应） | 12 小时 | 每日更新 |
| 壁纸（其他） | 5 分钟 | 保持随机性 |
| 激活码 | 1 小时 | 变化不频繁 |

### 缓存标识

响应头 `X-App-Cache` 标识缓存状态：
- `HIT`：命中缓存
- `MISS`：未命中，已请求 API

> 💡 未安装 APCu 扩展时，缓存功能自动禁用，不影响正常使用。

## 🔄 重试机制

所有接口内置自动重试机制，提高请求成功率：

- **重试次数**：最多 2 次
- **重试间隔**：300 毫秒
- **触发条件**：curl 请求失败或返回错误

当所有重试均失败时，接口返回 HTTP 500 错误。

## 🔧 高级配置

### 反代 GitHub API

为避免 GitHub API 请求限制，建议配置反向代理：

**Nginx 配置：**

```nginx
server {
    listen 8002;
    server_name _;

    location / {
        proxy_pass https://api.github.com;
        proxy_set_header Host api.github.com;
        proxy_set_header Authorization "token YOUR_GITHUB_TOKEN";

        # 缓存 1 小时
        proxy_cache_valid 200 1h;
        add_header X-Cache $upstream_cache_status;
    }
}
```

**修改 ghrelease 接口：**

```bash
# 将 API 地址替换为反代地址
sed -i 's|https://api.github.com|http://127.0.0.1:8002|g' ./ghrelease/index.php
```

## 🛡️ 安全说明

Docker 镜像默认启用以下安全配置：
- 禁用 `exec`、`system`、`passthru` 等危险函数
- 关闭 `expose_php`
- 禁止 `allow_url_fopen`
- 所有接口均进行参数校验和过滤

## 📝 更新日志

```bash
# 拉取最新代码
cd /opt/sdlp
git pull

# 强制更新（丢弃本地修改）
git fetch && git reset --hard origin/main

# Docker 更新
docker compose pull && docker compose up -d
```

## 📄 许可证

[MIT License](LICENSE)
