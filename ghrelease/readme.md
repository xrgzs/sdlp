# 自动跳转GitHub Release下载链接

这个 PHP API 用于查找指定 GitHub 仓库、指定版本的 release 文件，你可以传入参数来指定仓库、版本和搜索关键词。

> [!TIP]
> 如果Release的文件名固定，没有版本号等随机信息，直接使用官方链接即可自动获取 `latest` 下载链接，无需使用本仓库
>
> 例：<https://github.com/myusername/myrepo/releases/latest/download/setup-windows-x64.exe>
>
> 如果Release的文件名带有版本号等随机信息，无法自动获取最新下载链接，故产生本仓库

## 使用方法

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

```
http://your-domain/ghrelease?repo=myusername/myrepo&tag=1.0.0&search=win_x64
```

假设你想查找仓库 `myusername/myrepo` 中版本为 `1.0.0` 的 release 文件，且文件名包含关键词 `win_x64`。你可以访问以下链接：

```
http://your-domain/ghrelease?repo=myusername/myrepo&tag=1.0.0&search=win_x64
```

如果你想使用镜像 `auto`，则可以访问：

```
http://your-domain/ghrelease?repo=myusername/myrepo&tag=1.0.0&search=win_x64&mirror=auto
```

API 将直接跳转到匹配的 release 文件链接，或者提示未找到匹配的文件。

## 部署

为了最大程度上避免请求限制，请反向代理 Github API，并增加缓存，最好是在本地。如果您的服务器有很多人使用，建议本地hosts + 证书劫持GitHub API域名。

如果需要本地劫持 hosts 代理 Github API，请参考：<https://zhuanlan.zhihu.com/p/411165246>

修改`index.php`内`$api_url`变量的服务器地址为本地反代：

```php
$api_url = "http://127.0.0.1:12345/repos/{$repo}/releases/{$tags}";
```

> [!IMPORTANT]
> 注意：如果你使用的是基于docker环境的[1Panel面板](https://github.com/1Panel-dev/1Panel)，OpenResty容器使用`host`网络，PHP容器在`bridge`网络（如`172.18.0.0/16`），注意修改`127.0.0.1`/`localhost`为`bridge`网络的网关地址（如`172.18.0.1`），不然PHP容器会无法访问本地反代
