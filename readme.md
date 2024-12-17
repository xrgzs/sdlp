<div align="center">
<img src="https://github.com/xrgzs/sdlp/assets/26499123/1b2af287-6ee9-4795-9404-83b9687d7cf4" alt="XRSOFT_LOGO_ROUND_1024" width="20%" />

# 软件下载链接解析器

一个用于收集不同软件直接下载链接解析方式的后端项目，潇然系统的子项目之一🌟🚀

</div>

## 接口

### 自动跳转软件官网下载链接

#### 飞书

<http://your-domain/soft/feishu> 直接下载 飞书电脑版

#### 腾讯会议

<http://your-domain/soft/tencentmeeting> 直接下载 腾讯会议电脑版 64位

<http://your-domain/soft/tencentmeeting?x86> 直接下载 腾讯会议电脑版 32位

#### 千牛

<http://your-domain/soft/qianniu> 直接下载 千牛Windows x64版

<http://your-domain/soft/qianniu?param=x64> 直接下载 千牛Windows x64版

<http://your-domain/soft/qianniu?param=x32> 直接下载 千牛Windows x32版

#### 火绒安全软件电脑版

<http://your-domain/soft/huorong> 直接下载 火绒

#### QQ

<http://your-domain/soft/qq> 直接下载 QQ 传统版

参数：`param`: 获取内容

<http://your-domain/soft/qq?param=downloadUrl> 直接下载 QQ 传统版

<http://your-domain/soft/qq?param=ntDownloadUrl> 直接下载 NTQQ 32位

<http://your-domain/soft/qq?param=ntDownloadX64Url> 直接下载 NTQQ 64位

#### 微信

用不着解析，直接:

<http://dldir1v6.qq.com/weixin/Windows/WeChatSetup.exe>

<http://dldir1v6.qq.com/weixin/Windows/WeChatSetup_x86.exe>

<http://dldir1v6.qq.com/weixin/Windows/WeChat_for_XP_SP3_To_Vista.exe>

#### 微信输入法

<http://your-domain/soft/wetype> 直接下载 微信输入法

#### 百度拼音输入法

<http://your-domain/soft/baidupinyin> 直接下载 百度拼音输入法

#### 向日葵

参数：`name` 软件名称

参数：`x64` 64位版本

<http://your-domain/soft/sunlogin?name=SUNLOGIN_X_WINDOWS> 直接下载 向日葵个人版 32位

<http://your-domain/soft/sunlogin?name=SUNLOGIN_X_WINDOWS&x64=1> 直接下载 向日葵个人版 64位

<http://your-domain/soft/sunlogin?name=SUNLOGIN_WINDOWS> 直接下载 向日葵企业版 控制端 32位

<http://your-domain/soft/sunlogin?name=SUNLOGIN_WINDOWS&x64=1> 直接下载 向日葵企业版 控制端 64位

<http://your-domain/soft/sunlogin?name=SLRC_WINDOWS_ENT> 直接下载 向日葵企业版 客户端 32位

<http://your-domain/soft/sunlogin?name=SLRC_WINDOWS_ENT&x64=1> 直接下载 向日葵企业版 客户端 64位

<http://your-domain/soft/sunlogin?name=SL_WINDOWS_LITE> 直接下载 向日葵SOS版 32位

<http://your-domain/soft/sunlogin?name=SL_WINDOWS_LITE&x64=1> 直接下载 向日葵SOS版 64位

#### RayLink

<http://your-domain/soft/raylink> 直接下载 RayLink 完整版

<http://your-domain/soft/raylink?lite> 直接下载 RayLink Lite版

#### 连连控

<http://your-domain/soft/asklink> 直接下载 连连控

#### 天翼网盘

<http://your-domain/soft/ecloud> 直接下载 天翼网盘

### 自动跳转360宝库软件下载链接

参数：`appid`

如软件下载页面 <http://baoku.360.cn/soft/show/appid/104693057> ，`104693057`即为`appid`

<http://your-domain/360baoku/?appid=104693057> 即可直接跳转到下载链接

### 自动跳转联想软件商店下载链接

参数：`softid`

如软件下载页面 <https://lestore.lenovo.com/detail/13407> ，`13407`即为`softid`

<http://your-domain/lestore/?softid=13407> 即可直接跳转到下载链接

### 自动跳转QQ软件中心下载链接

参数：`softid`

如软件下载页面 <https://pc.qq.com/detail/11/detail_351.html> ，`351`即为`softid`

<http://your-domain/qqsoft/?softid=351> 即可直接跳转到下载链接

### 自动跳转QAX软件中心下载链接

参数：`softid`

<http://your-domain/qaxsoft/?softid=103352> 即可直接跳转到下载链接

### 自动跳转HPM模块下载链接

参数：`name`

根据给定名称作为前缀搜索，优先匹配最新上传的版本

<http://your-domain/hpm/?name=ToDesk完整版> 即可直接跳转到 ToDesk完整版 最新上传的下载链接

### 自动跳转Scoop软件下载链接

参数：

- `name`：软件名称
- `bucket`：存储库名称，默认为 `DoveBoy/ScoopMaster`
- `branch`：存储库名称，默认为 `master`
- `arch`：架构，`64bit`、`32bit`、`arm64` 等，默认为 `64bit`

软件搜索：<https://scoop.sh/>

<http://your-domain/scoop/?name=aria2> 即可直接跳转到下载链接，并且使用加速过的链接

<http://your-domain/scoop/?name=ecloud&bucket=xrgzs/sdoog&branch=master> 指定 bucket 和 master 分支

### 自动跳转Windows ISO官方下载地址

此接口内容复杂，请前往 `msdl` 目录阅读 `readme.md` 文件

### 自动跳转GitHub Release下载链接

参数：

- `repo`：（必填）GitHub 仓库名称，例如 `myusername/myrepo`

- `tag`：（可选）版本号，例如 `1.0.0`

  - 如果未指定，默认使用 `latest`

- `search`：（可选）搜索关键词，例如 `win_x64`

  - 如果未指定，默认匹配最后一个 release 文件

- `filter`：（可选）二次搜索结尾关键词，例如 `.exe`

  - 如果未指定`search`，此参数无效
  - 在匹配文件扩展名发生冲突，如误匹配到 `.pdb`、`.blockmap`、`.sig` 等文件时可以使用

- `mirror`：（可选）使用加速镜像，如 `auto`

示例：

假设你想查找仓库 `myusername/myrepo` 中版本为 `latest` 的第一个 release 文件。你可以访问以下链接：

<http://your-domain/ghrelease?repo=myusername/myrepo&tag=1.0.0&search=win_x64>

假设你想查找仓库 `myusername/myrepo` 中版本为 `1.0.0` 的 release 文件，且文件名包含关键词 `win_x64`。你可以访问以下链接：

<http://your-domain/ghrelease?repo=myusername/myrepo&tag=1.0.0&search=win_x64>

如果你想使用镜像 `auto`，则可以访问：

<http://your-domain/ghrelease?repo=myusername/myrepo&tag=1.0.0&search=win_x64&mirror=auto>

API 将直接跳转到匹配的 release 文件链接，或者提示未找到匹配的文件。

### 蓝奏API

来自：<https://github.com/hanximeng/LanzouAPI>

参数：

- `url`：蓝奏云外链链接
- `type`：是否直接下载 值：`down`
- `pwd`：外链密码

直接下载：

- 无密码：<http://your-domain/lanzou/?url=https://www.lanzous.com/i6th9cd&type=down>
- 有密码：<http://your-domain/lanzou/?url=https://www.lanzous.com/i42Xxebssfg&type=down&pwd=1234>

输出直链：

- 无密码：<http://your-domain/lanzou/?url=https://www.lanzous.com/i6th9cd>
- 有密码：<http://your-domain/lanzou/?url=https://www.lanzous.com/i42Xxebssfg&pwd=1234>

## 部署

- HTTP Web Server
  - 此处使用 1Panel 环境

- PHP 建议使用8.1+
  - 启用 `CURL`扩展
- 克隆本仓库到服务器的网站目录
- 部分需要配置本地反代，并替换文件内接口
- PHP是“最好的”语言，所以请务必配置 WAF

## 安装

创建运行环境：PHP 8，带上扩展 `curl`

创建网站：运行环境 PHP 8，主域名：`your-domain`

进入网站目录，打开终端

```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/your-domain/index
```

克隆此仓库：

```bash
git clone https://gh.xrgzs.top/https://github.com/xrgzs/sdlp.git
```

配置 NGINX：运行目录 `/sdlp`

```nginx
root /www/sites/your-domain/index/sdlp; 
```

## 更新

```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/your-domain/index/sdlp
git pull
```

强制更新：

```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/your-domain/index/sdlp
git fetch && git reset --hard origin/main
```

## 配置

### 反代 GitHub API

为了最大程度上避免请求限制，请反向代理 GitHub API，设置您自己的 GitHub Token 并增加缓存，最好是在本地。如果您的服务器有很多人使用，建议本地 hosts + 证书劫持 GitHub API 域名。

如果需要本地劫持 hosts 代理 Github API，请参考：<https://zhuanlan.zhihu.com/p/411165246>

创建网站：反向代理，主域名：`api.gh.local:8002`（虚构），代理地址：`https://api.github.com`

注意：此处的8002端口并未建站，使用机器的任意IP、任意域名访问8002端口均为GitHub API反代，建议配置机器防火墙，不要让外部访问，如果不方便配置，那就填写 1panel-network 的地址：`172.18.0.1:8002`, 使用 IP + 端口 访问避免设置 PHP 容器的 hosts

替换反向代理内容：Authorization后面的内容为您的GitHub Token，此处增加了1h的缓存

```nginx
location ^~ / {
    proxy_pass https://api.github.com; 
    proxy_set_header Host api.github.com; 
    proxy_set_header X-Real-IP $remote_addr; 
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; 
    proxy_set_header REMOTE-HOST $remote_addr; 
    proxy_set_header Upgrade $http_upgrade; 
    proxy_set_header Connection "upgrade"; 
    proxy_set_header X-Forwarded-Proto $scheme; 
    proxy_set_header Authorization "***********************************************";
    proxy_http_version 1.1; 
    add_header X-Cache $upstream_cache_status; 
    proxy_ignore_headers Set-Cookie Cache-Control expires; 
    proxy_cache proxy_cache_panel; 
    proxy_cache_key $host$uri$is_args$args; 
    proxy_cache_valid 200 1h; 
}
```

替换 ghrelease 反代内容：此处反代 api.github.com 到 1panel-network 的 8002 端口

```bash
cd /opt/1panel/apps/openresty/openresty/www/sites/your-domain/index/sdlp
gateway_ip=$(docker network inspect 1panel-network | grep '"Gateway"' | awk -F'"' '{print $4}')
echo "Gateway IP: $gateway_ip"
sed -i "s/https:\/\/api.github.com/http:\/\/$gateway_ip:8002/g" ./ghrelease/index.php
```

每次更新后都需要执行上面的内容
