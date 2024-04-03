<div align="center">
<img src="https://github.com/xrgzs/sdlp/assets/26499123/1b2af287-6ee9-4795-9404-83b9687d7cf4" alt="XRSOFT_LOGO_ROUND_1024" width="20%" />

# 软件下载链接解析器

一个用于收集不同软件直接下载链接解析方式的后端项目，潇然系统的子项目之一🌟🚀

</div>

## 部署

- HTTP Web Server
- PHP 建议使用8.1+
  - 启用 `CURL`、`JSON` 扩展
- 克隆本仓库到服务器的网站目录
- 部分需要配置本地反代，并替换文件内接口

## 接口

### 自动跳转软件官网下载链接

#### 飞书

http://your-domain/soft/feishu 直接下载 飞书电脑版

#### 火绒安全软件电脑版

http://your-domain/soft/huorong 直接下载 火绒

#### QQ

http://your-domain/soft/qq 直接下载 QQ 传统版

参数：`param`: 获取内容

http://your-domain/soft/qq?param=downloadUrl 直接下载 QQ 传统版

http://your-domain/soft/qq?param=ntDownloadUrl 直接下载 NTQQ 32位

http://your-domain/soft/qq?param=ntDownloadX64Url 直接下载 NTQQ 64位

#### 微信

用不着解析，直接 http://dldir1.qq.com/weixin/Windows/WeChatSetup.exe

#### 微信输入法

http://your-domain/soft/wetype 直接下载 微信输入法

### 自动跳转360宝库软件下载链接

参数：`appid`

如软件下载页面 http://baoku.360.cn/soft/show/appid/104693057 ，`104693057`即为`appid`

http://your-domain/360baoku/?appid=104693057 即可直接跳转到下载链接


### 自动跳转联想软件商店下载链接

参数：`softid`

如软件下载页面 https://lestore.lenovo.com/detail/13407 ，`13407`即为`softid`

http://your-domain/lestore/?softid=13407 即可直接跳转到下载链接

### 自动跳转QQ软件中心下载链接

参数：`softid`

如软件下载页面 https://pc.qq.com/detail/11/detail_351.html，`351`即为`softid`

http://your-domain/qqsoft/?softid=351 即可直接跳转到下载链接

### 自动跳转Windows ISO官方下载地址

**无需此接口不建议部署**，直接删掉 `msdl` 目录

此接口内容复杂，请前往 `msdl` 目录阅读 `readme.md` 文件

### 自动跳转GitHub Release下载链接

**无需此接口不建议部署**，直接删掉 `ghrelease` 目录

此接口内容复杂，请前往 `ghrelease` 目录阅读 `readme.md` 文件

