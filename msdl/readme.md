# 自动跳转Windows ISO官方下载地址

## 使用方式

### 无参数

直接下载 ***Windows 11 23H2 v2 (Build 22631.2861) - Chinese Simplified***

### 有参数

参数：

- `product_id`：产品ID

  - 使用python爬取：https://github.com/ave9858/msdls

  - ❤爬取好的数据：https://github.com/gravesoft/msdl/raw/main/data/products.json

    | `product_id` | 对应产品                                         |
    | ------------ | ------------------------------------------------ |
    | 48           | Windows 8.1 Single Language (Build 9600.17415)   |
    | 52           | Windows 8.1 (Build 9600.17415)                   |
    | 55           | Windows 8.1 N (Build 9600.17415)                 |
    | 61           | Windows 8.1 K (Build 9600.17415)                 |
    | 62           | Windows 8.1 KN (Build 9600.17415)                |
    | 2378         | Windows 10 22H2 Home China (Build 19045.2006)    |
    | 2618         | Windows 10 22H2 v1 (Build 19045.2965)            |
    | 2935         | Windows 11 23H2 v2 (Build 22631.2861)            |
    | 2936         | Windows 11 23H2 Home China v2 (Build 22631.2861) |

  - 网页获取：https://techbench.betaworld.cn/products.php?prod=all

- `sku_id`：版本ID

  | `sku_id` | 对应版本                             |
  | -------- | ------------------------------------ |
  | 17433    | Arabic                               |
  | 17460    | Brazilian Portuguese                 |
  | 17434    | Bulgarian                            |
  | 17435    | Chinese Simplified (Home China only) |
  | 17436    | Chinese Simplified                   |
  | 17437    | Chinese Traditional                  |
  | 17438    | Croatian                             |
  | 17439    | Czech                                |
  | 17440    | Danish                               |
  | 17441    | Dutch                                |
  | 17442    | English (United States)              |
  | 17443    | English International                |
  | 17444    | Estonian                             |
  | 17445    | Finnish                              |
  | 17446    | French                               |
  | 17447    | French Canadian                      |
  | 17448    | German                               |
  | 17449    | Greek                                |
  | 17450    | Hebrew                               |
  | 17451    | Hungarian                            |
  | 17452    | Italian                              |
  | 17453    | Japanese                             |
  | 17454    | Korean                               |
  | 17455    | Latvian                              |
  | 17456    | Lithuanian                           |
  | 17457    | Norwegian                            |
  | 17458    | Polish                               |
  | 17459    | Portuguese                           |
  | 17461    | Romanian                             |
  | 17462    | Russian                              |
  | 17463    | Serbian Latin                        |
  | 17464    | Slovak                               |
  | 17465    | Slovenian                            |
  | 17466    | Spanish                              |
  | 17467    | Spanish (Mexico)                     |
  | 17468    | Swedish                              |
  | 17469    | Thai                                 |
  | 17470    | Turkish                              |
  | 17471    | Ukrainian                            |

- `arch`：系统架构
  - x86
  - x64

#### 下载 Windows 11 64位 多版本中文版

1. 不加参数
2. ?product_id=2935
3. ?sku_id=17436
4. ?product_id=2935&sku_id=17436
5. ?product_id=2935&arch=x64
6. **?product_id=2935&sku_id=17436&arch=x64**

默认包含 6 个 SKU

![](https://img10.360buyimg.com/babel/jfs/t20260320/226374/17/14494/42161/65fae5a3Fd2676089/f4a83e7e4155d265.png)

#### 下载 Windows 11 64位 家庭中文版（中国特供版）

1. ?product_id=2936&sku_id=17435
2. **?product_id=2936&sku_id=17435&arch=x64**

默认包含 1 个 SKU

#### 下载 Windows 10 64位 多版本中文版

1. ?product_id=2618
2. ?product_id=2618&sku_id=17436
3. **?product_id=2618&sku_id=17436&arch=x64**

#### 下载 Windows 10 32位 多版本美式英文版

**?product_id=2618&sku_id=17442&arch=x86**

……以此类推
