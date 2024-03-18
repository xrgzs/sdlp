# 自动跳转GitHub Release下载链接

这个 PHP API 用于查找指定 GitHub 仓库、指定版本的 release 文件，你可以传入参数来指定仓库、版本和搜索关键词。

> [!TIP]
> 如果Release的文件名固定，没有版本号等随机信息，直接使用官方链接即可自动获取 `latest` 下载链接，无需使用本仓库
>
> 例：https://github.com/myusername/myrepo/releases/latest/download/setup-windows-x64.exe
>
> 如果Release的文件名带有版本号等随机信息，无法自动获取最新下载链接，故产生本仓库

## 使用方法

参数：

- `repo`：（必填）GitHub 仓库名称，例如 `myusername/myrepo`

- `tag`：（可选）版本号，例如 `1.0.0`

  - 如果未指定，默认使用 `latest`

- `search`：（可选）搜索关键词，例如 `win_x64`

  - 如果未指定，默认匹配最后一个 release 文件

- `mirror`：（可选）镜像名称，例如 `ghproxy`

  - 如果未指定，则不使用镜像，默认使用 GitHub 官方下载链接

  - 镜像名称使用简短别名：

    | 镜像名称  | 对应地址                    | 备注                                                         |
    | --------- | --------------------------- | ------------------------------------------------------------ |
    | `ghproxy` | https://mirror.ghproxy.com/ | [日本、韩国、德国等]（CDN 不固定） - 该公益加速源由 [ghproxy] 提供 提示：希望大家尽量多使用前面的美国节点（每次随机 负载均衡）， 避免流量都集中到亚洲公益节点，减少成本压力，公益才能更持久~ |
    | `pig`     | https://dl.ghpig.top/       | [美国 Cloudflare CDN] - 该公益加速源由 [feizhuqwq.com] 提供  |
    | `ddlc`    | https://dgh.ddlc.top/       | [美国 Cloudflare CDN] - 该公益加速源由 [@mtr-static-official] 提供 |
    | `slink`   | https://slink.ltd/          | [美国 Cloudflare CDN] - 该公益加速源由 [知了小站] 提供       |
    | `con`     | https://gh.con.sh/          | [美国 Cloudflare CDN] - 该公益加速源由 [佚名] 提供           |

示例：

假设你想查找仓库 `myusername/myrepo` 中版本为 `latest` 的第一个 release 文件。你可以访问以下链接：

```
github-release-api.php?repo=myusername/myrepo&tag=1.0.0&search=win_x64
```

假设你想查找仓库 `myusername/myrepo` 中版本为 `1.0.0` 的 release 文件，且文件名包含关键词 `win_x64`。你可以访问以下链接：

```
github-release-api.php?repo=myusername/myrepo&tag=1.0.0&search=win_x64
```

如果你想使用镜像 `pig`，则可以访问：

```
github-release-api.php?repo=myusername/myrepo&tag=1.0.0&search=win_x64&mirror=pig
```

API 将直接跳转到匹配的 release 文件链接，或者提示未找到匹配的文件。

## 部署

为了最大程度上避免请求限制，请反向代理 Github API，并增加缓存，最好是在本地。如果您的服务器有很多人使用，建议本地hosts + 证书劫持GitHub API域名。

另外，本地代理也有其他好处，例如可以避免很多因“国家公用电信网提供的国际出入口信道”连接不稳定而导致的错误问题，且无需“自行建立信道进行国际联网”，本地代理出来只能在本地访问，完全合规合法

1. 下载`ghrelease.php`

2. （非必须，但很关键）配置 nginx 反向代理 https://api.github.com，并增加缓存，可以在服务器面板里面图形化设置

   ```nginx
   http {
       # 定义缓存路径、层级结构、键区大小和最大缓存大小
       proxy_cache_path /var/cache/nginx/github_api_cache levels=1:2 keys_zone=github_api_cache:10m inactive=60m max_size=500m;
   
       # 设置缓存清理策略，这里设定为当缓存超过80%容量时开始删除最少使用的缓存项
       proxy_cache_use_stale updating error timeout invalid_header http_500 http_502 http_503 http_504;
       proxy_cache_background_update on;
       proxy_cache_min_uses 1;
       proxy_cache_lock on;
       proxy_cache_lock_timeout 5s;
       proxy_cache_revalidate on;
       proxy_cache_bypass $http_pragma;
       proxy_no_cache $http_authorization; # 不缓存带有Authorization头的请求，一般用于保护隐私和安全
   
       upstream github_api {
           server api.github.com:443 ssl http2;
       }
   
       server {
           listen 12345;
           server_name 127.0.0.0/8;
   
           location / {
               resolver 223.5.5.5; # 使用公共DNS或其他合适的DNS服务器
               client_max_body_size 10m; # 设定客户端上传的请求体大小限制
   
               proxy_pass https://github_api;
               proxy_cache github_api_cache;
               proxy_cache_methods GET HEAD; # 根据GitHub API实际情况决定是否缓存POST等方法
               proxy_cache_key "$scheme$request_method$host$request_uri";
               proxy_cache_valid 200 1d; # 示例缓存策略，缓存成功响应至少一天，可根据实际情况调整
   
               # 对于HTTPS转发以及支持缓存的必要配置
               proxy_set_header Host $host;
               proxy_set_header Accept-Encoding gzip;
               proxy_set_header Proxy "";
               proxy_ssl_server_name on;
   
               # 可选添加缓存状态到响应头供调试
               add_header X-Cache-Status $upstream_cache_status;
           }
       }
   }
   ```

   如果需要本地劫持 hosts 代理 Github API，请参考：https://zhuanlan.zhihu.com/p/411165246

   修改`ghrelease.php`内`$api_url`变量的服务器地址为本地反代

   ```php
   $api_url = "http://127.0.0.1:12345/repos/{$repo}/releases/{$tags}";
   ```

> [!IMPORTANT]
> 注意：如果你使用的是基于docker环境的[1Panel面板](https://github.com/1Panel-dev/1Panel)，OpenResty容器使用`host`网络，PHP容器在`bridge`网络（如`172.18.0.0/16`），注意修改`127.0.0.1`/`localhost`为`bridge`网络的网关地址（如`172.18.0.1`），不然PHP容器会无法访问本地反代

3. 配置服务器 PHP 环境

4. 将修改后的 `ghrelease.php` 上传到服务器的网站目录

5. 测试访问接口，并检查缓存是否正常