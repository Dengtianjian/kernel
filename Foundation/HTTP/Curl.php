<?php

namespace kernel\Foundation\HTTP;

use kernel\Foundation\Data\Arr;

/**
 * cURL HTTP 客户端封装
 *
 * 基于 PHP 原生 cURL 扩展实现，提供链式（Fluent）API，同时兼容 PHP 8 下的
 * CurlHandle 对象句柄（判句柄存活统一使用 is_object()，不可用 is_resource()）。
 *
 * ## 基本用法
 *
 * 采用"先配置、后发送"的两段式链式调用：
 *
 * ```php
 * use kernel\Foundation\HTTP\Curl;
 *
 * // 方式一：静态工厂 + 链式发送（发送方法内部自动调用 send()）
 * $result = Curl::init()
 *     ->url('https://api.example.com/users')
 *     ->data(['name' => '张三'])
 *     ->headers(['X-Token' => 'abc'])
 *     ->post()                       // 立即发送 POST
 *     ->responseData();              // 读取响应（JSON 自动解析为数组）
 *
 * // 方式二：new 实例 + 手动 send()
 * $curl = new Curl();
 * $curl->url('https://api.example.com/users')->data(['page' => 1]);
 * $curl->send();
 * echo $curl->statusCode();          // 200
 * var_dump($curl->responseHeaders()); // 响应头
 * ```
 *
 * ## 请求流程
 *
 * 1. 构造时 curl_init() 创建句柄。
 * 2. 通过 url()/data()/headers()/cookie()/json()/sslCert() 等配置请求。
 * 3. 调用 get()/post()/put()/patch()/delete()/head()/connect()/file() 之一发送
 *    （发送方法内部会调用 send() 并返回 $this，便于继续链式读响应）。
 * 4. 通过 responseData()/responseHeaders()/statusCode() 读取结果，
 *    失败时通过 errorNo()/error() 获取 curl 错误号/错误信息。
 *
 * ## 支持的请求方法
 *
 * | 方法            | HTTP 动词   | 说明                                            |
 * |-----------------|-------------|-------------------------------------------------|
 * | get()           | GET         | 查询参数自动拼接至 URL                           |
 * | post()          | POST        | 表单或 JSON 请求体（默认）                       |
 * | put()           | PUT         | 可整体替换请求体                                 |
 * | patch()         | PATCH       | 可整体替换请求体                                 |
 * | delete()        | DELETE      | 可携带请求体                                     |
 * | head()          | HEAD        | 无请求体，仅响应头                               |
 * | connect()       | CONNECT     | 代理隧道建连                                     |
 * | file()/upload() | POST/multipart | 文件上传（multipart/form-data）                |
 *
 * ## 响应读取
 *
 * - responseData()：响应体，Content-Type 为 JSON 时自动 json_decode 为数组
 * - getData()：responseData() 的别名，兼容旧用法
 * - responseHeaders()：响应头（关联数组）
 * - statusCode()：HTTP 状态码（请求失败时为 0）
 * - errorNo()/error()：curl 错误号 / 错误信息
 *
 * ## 注意事项
 *
 * - Content-Type 会按请求类型自动强制覆盖，无需手动设置：
 *   文件上传 → multipart/form-data；GET/HEAD → 移除；JSON → application/json；
 *   否则 → application/x-www-form-urlencoded。
 * - 同一实例可 reset() 重置配置后复用（句柄保留），适合循环批量请求。
 * - 不要在请求失败后依赖 responseData()，应优先判断 errorNo()/statusCode()。
 */
class Curl
{
    /**
     * 请求地址
     *
     * @var string
     */
    protected $requestUrl = null;
    /**
     * 请求头集合
     *
     * 关联数组 key 为请求头名称，value 为请求头值，例如：
     * ["Content-Type" => "application/json", "X-Token" => "xxx"]
     *
     * @var array
     */
    protected $curlHeaders = [
        'Accept' => 'application/json, text/javascript, */*; q=0.01',
        'Content-Type' => 'application/json'
    ];
    /**
     * 请求体
     *
     * @var array
     */
    protected $curlDatas = [];
    /**
     * 请求方法：get/post/put/patch/delete/head/file 等
     *
     * @var string
     */
    protected $curlMethod = 'get';
    /**
     * 请求超时时间（秒）
     *
     * @var int
     */
    protected $curlTimeout = 60;
    /**
     * 连接超时时间（秒），0 表示不单独设置
     *
     * @var int
     */
    protected $curlConnectTimeout = 0;
    /**
     * 请求体是否为 JSON
     *
     * @var bool
     */
    protected $isJson = true;
    /**
     * Cookie 数据
     *
     * @var array
     */
    protected $curlCookie = [];
    /**
     * 上传文件集合，key 为表单字段名，value 为 CURLFile 或文件路径
     *
     * @var array
     */
    protected $uploadFile = [];
    /**
     * 是否跳过 SSL 证书校验
     *
     * @var bool
     */
    protected $bypassSslVerification = false;
    /**
     * 客户端 SSL 证书类型
     *
     * @var string
     */
    protected $sslCertType = 'PEM';
    /**
     * 客户端 SSL 证书文件路径
     *
     * @var string|null
     */
    protected $sslCertFilePath = null;
    /**
     * 客户端 SSL 密钥类型
     *
     * @var string
     */
    protected $sslKeyType = 'PEM';
    /**
     * 客户端 SSL 密钥文件路径
     *
     * @var string|null
     */
    protected $sslKeyFilePath = null;
    /**
     * 可传递的额外 cURL 选项
     *
     * @var array
     */
    protected $curlOptions = [];
    /**
     * 响应数据（JSON 自动解析为数组）
     *
     * @var mixed
     */
    protected $responseData = null;
    /**
     * cURL 错误信息
     *
     * @var string|null
     */
    protected $curlErrorMsg = null;
    /**
     * cURL 错误码
     *
     * @var int|null
     */
    protected $curlErrorNo = null;
    /**
     * 响应头集合
     *
     * @var array
     */
    protected $responseHeadersData = null;
    /**
     * HTTP 响应状态码
     *
     * @var int
     */
    protected $responseStatusCode = 200;
    /**
     * 代理设置
     *
     * @var array
     */
    protected $proxy = [
        'open' => false,
        'url' => '',
        'port' => '',
        'username' => '',
        'password' => ''
    ];
    /**
     * cURL 句柄（PHP 8 下为 CurlHandle 对象）
     *
     * @var \CurlHandle|\resource|null
     */
    protected $curlInstance = null;

    /**
     * 构造函数
     *
     * 创建 cURL 句柄。PHP 8 下 curl_init() 返回 \CurlHandle 对象，
     * 因此后续判句柄存活统一使用 is_object() 而非 is_resource()。
     */
    public function __construct()
    {
        $this->curlInstance = curl_init();
    }

    /**
     * 创建实例（静态工厂）
     *
     * 返回 new static()，支持子类继承时返回子类实例。适合无需复用句柄、
     * 一次性发起请求的场景（例如 Router 中的快速 HTTP 调用）。
     *
     * ```php
     * $res = Curl::init()->url($url)->post()->getData();
     * ```
     *
     * @return static 新实例
     */
    public static function init()
    {
        return new static();
    }

    /**
     * 构建请求头数组
     *
     * 关联数组转换为 ["Key: value", ...]；列表数组原样返回。
     *
     * @param array $headers 请求头
     * @return array
     */
    protected function buildHeaders($headers)
    {
        $buildHeaders = [];
        if (Arr::isAssoc($headers)) {
            foreach ($headers as $key => $value) {
                $buildHeaders[] = $key . ': ' . $value;
            }
        } else {
            $buildHeaders = $headers;
        }
        return $buildHeaders;
    }

    /**
     * 构建 Cookie 字符串
     *
     * @param array $cookies Cookie 集合
     * @return string
     */
    protected function buildCookie($cookies)
    {
        if (!$cookies) {
            return '';
        }
        $cookie = '';
        if (Arr::isAssoc($cookies)) {
            $cookieDatas = [];
            foreach ($cookies as $key => $value) {
                $cookieDatas[] = $key . '=' . $value;
            }
            $cookie = implode('; ', $cookieDatas);
        } else {
            $cookie = implode('; ', $cookies);
        }
        return $cookie;
    }

    /**
     * 拼接查询参数到 URL
     *
     * 自动处理 URL 中已存在的 "?"，使用 "?" 或 "&" 分隔。
     *
     * @param string $url    基础 URL
     * @param array  $params 查询参数
     * @return string
     */
    protected function appendQuery($url, $params)
    {
        if (!$params) {
            return $url;
        }
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . http_build_query($params);
    }

    /**
     * 设置请求地址
     *
     * @param string $url   请求地址
     * @param array  $query 可选查询参数（会自动以 ? / & 拼接到 URL）
     * @return $this
     */
    public function url($url, $query = [])
    {
        $this->requestUrl = $this->appendQuery($url, $query);
        return $this;
    }

    /**
     * 设置请求方法为 GET 并发送
     *
     * 发送 GET 请求。传入的 $query 会与 url() 中已拼接的查询参数合并追加到 URL；
     * 若通过 data() 设置了请求体，也会一并拼接到 URL 查询串（GET 不携带请求体）。
     *
     * ```php
     * $res = Curl::init()->url('https://api.example.com/users')->get(['page' => 1])->responseData();
     * ```
     *
     * @param array $query 可选查询参数（追加到 URL 查询串）
     * @return $this 便于继续链式读取响应
     */
    public function get($query = [])
    {
        $this->curlMethod = 'get';
        if ($query) {
            $this->requestUrl = $this->appendQuery($this->requestUrl, $query);
        }
        return $this->send();
    }

    /**
     * 设置请求方法为 POST 并发送
     *
     * 发送 POST 请求。默认使用 data() 设置的请求体；若传入 $body 则整体替换。
     * 请求体序列化方式取决于 json()：
     * - json(true)（默认）→ application/json + json_encode
     * - json(false) → application/x-www-form-urlencoded + http_build_query
     *
     * ```php
     * $res = Curl::init()
     *     ->url('https://api.example.com/users')
     *     ->data(['name' => '张三', 'age' => 20])
     *     ->post()
     *     ->responseData();
     * ```
     *
     * @param array|string|null $body 请求体；null 表示使用 data() 设置的数据
     * @return $this 便于继续链式读取响应
     */
    public function post($body = null)
    {
        $this->curlMethod = 'post';
        if (!is_null($body)) {
            $this->curlDatas = $body;
        }
        return $this->send();
    }

    /**
     * 设置请求方法为 PUT 并发送
     *
     * 发送 PUT 请求，用于整体更新资源。默认使用 data() 设置的请求体，
     * 传入 $body 则整体替换（与 PATCH 的"部分更新"语义相对）。
     *
     * ```php
     * $res = Curl::init()
     *     ->url('https://api.example.com/users/1')
     *     ->put(['name' => '李四'])
     *     ->responseData();
     * ```
     *
     * @param array|string|null $body 请求体；null 表示使用 data() 设置的数据
     * @return $this 便于继续链式读取响应
     */
    public function put($body = null)
    {
        $this->curlMethod = 'put';
        if (!is_null($body)) {
            $this->curlDatas = $body;
        }
        return $this->send();
    }

    /**
     * 设置请求方法为 PATCH 并发送
     *
     * 发送 PATCH 请求，用于部分更新资源。默认使用 data() 设置的请求体，
     * 传入 $body 则整体替换。
     *
     * ```php
     * $res = Curl::init()
     *     ->url('https://api.example.com/users/1')
     *     ->patch(['age' => 21])
     *     ->responseData();
     * ```
     *
     * @param array|string|null $body 请求体；null 表示使用 data() 设置的数据
     * @return $this 便于继续链式读取响应
     */
    public function patch($body = null)
    {
        $this->curlMethod = 'patch';
        if (!is_null($body)) {
            $this->curlDatas = $body;
        }
        return $this->send();
    }

    /**
     * 设置请求方法为 DELETE 并发送
     *
     * 发送 DELETE 请求，删除资源。部分服务允许携带请求体，可通过 data()
     * 或 $body 参数设置；大多数服务忽略请求体。
     *
     * ```php
     * $res = Curl::init()->url('https://api.example.com/users/1')->delete()->responseData();
     * ```
     *
     * @param array|string|null $body 请求体；null 表示使用 data() 设置的数据
     * @return $this 便于继续链式读取响应
     */
    public function delete($body = null)
    {
        $this->curlMethod = 'delete';
        if (!is_null($body)) {
            $this->curlDatas = $body;
        }
        return $this->send();
    }

    /**
     * 设置请求方法为 HEAD 并发送
     *
     * 发送 HEAD 请求，仅获取响应头（不返回响应体），常用于探测资源存在性、
     * 读取响应头元信息等。通过 CURLOPT_NOBODY 实现。
     *
     * ```php
     * $res = Curl::init()->url('https://api.example.com/files/1')->head();
     * $status = $res->statusCode();          // 200 表示存在
     * $length = $res->responseHeaders()['Content-Length'] ?? null;
     * ```
     *
     * @return $this 便于继续链式读取响应头
     */
    public function head()
    {
        $this->curlMethod = 'head';
        return $this->send();
    }

    /**
     * 设置请求方法为 CONNECT 并发送
     *
     * 发送 CONNECT 请求，用于建立 HTTP 代理隧道（TLS 通过代理）。仅当目标
     * 服务通过代理建立 CONNECT 隧道时使用，一般场景较少涉及。
     *
     * @return $this 便于继续链式读取响应
     */
    public function connect()
    {
        $this->curlMethod = 'connect';
        return $this->send();
    }

    /**
     * 文件上传（multipart/form-data）
     *
     * 两种用法：
     * 1. 直接传入文件路径 + 字段名，随后调用 post()（或 send()）触发上传；
     * 2. 先 upload() 注册多个 CURLFile、data() 设置普通表单字段，再 file() 声明
     *    本次为上传并触发。
     *
     * 注意：file() 本身**不会自动发送**，仅将请求方法标记为 multipart 上传，
     * 必须由后续的 post()/send() 真正发起请求。
     *
     * ```php
     * // 用法一：直接传路径上传单文件
     * $res = Curl::init()
     *     ->url('https://api.example.com/upload')
     *     ->file('/path/to/a.jpg')
     *     ->post()
     *     ->responseData();
     *
     * // 用法二：混合普通字段 + 多文件
     * $curl = Curl::init()->url('https://api.example.com/upload');
     * $curl->upload('pic1', '/path/a.jpg');
     * $curl->upload('pic2', '/path/b.png');
     * $curl->data(['title' => '相册']);
     * $res = $curl->file()->post()->responseData();
     * ```
     *
     * @param string|null $filePath  文件路径（可选，可直接上传单文件）
     * @param string|null $fieldName 表单字段名（默认 "file"）
     * @param string|null $fileName  上传文件名（默认取路径 basename）
     * @param string|null $mimeType  文件 MIME 类型（默认由 curl 推断）
     * @return $this 便于继续链式调用
     */
    public function file($filePath = null, $fieldName = 'file', $fileName = null, $mimeType = '')
    {
        $this->curlMethod = 'file';
        if ($filePath) {
            $this->uploadFile[$fieldName] = new \CURLFile($filePath, $mimeType ?: '', $fileName ?: basename($filePath));
        }
        // 注意：file() 仅配置上传，不自动发送，由后续 post()/send() 触发
        return $this;
    }

    /**
     * 注册一个上传文件
     *
     * 将文件注册到上传集合（不触发发送），可多次调用上传多个文件。
     * 之后通过 file() 或 post() 触发 multipart/form-data 上传。
     *
     * ```php
     * $curl = Curl::init()->url($url);
     * $curl->upload('file', '/path/a.jpg');
     * $curl->data(['note' => 'hello']);
     * $res = $curl->file()->post()->responseData();
     * ```
     *
     * @param string $fieldName  表单字段名（服务端 $_FILES 的 key）
     * @param string $filePath   文件绝对路径
     * @param string $fileName   上传文件名（默认取路径 basename）
     * @param string $mimeType   文件 MIME 类型（默认由 curl 推断）
     * @return $this 便于继续链式调用
     */
    public function upload($fieldName, $filePath, $fileName = null, $mimeType = '')
    {
        $this->uploadFile[$fieldName] = new \CURLFile($filePath, $mimeType, $fileName ?: basename($filePath));
        return $this;
    }

    /**
     * 设置请求体数据
     *
     * 为请求设置主体数据。序列化规则由 json() 决定：
     * - json(true)（默认）：关联数组会 json_encode 为 JSON 字符串；
     * - json(false)：数组会 http_build_query 为表单串；
     * - 字符串：原样作为请求体。
     * 对 GET 请求，data() 设置的数据会自动拼接到 URL 查询串。
     *
     * ```php
     * $curl = Curl::init()->url($url)->data(['a' => 1, 'b' => 2]);
     * ```
     *
     * @param array|string $data 请求体数据
     * @return $this
     */
    public function data($data)
    {
        $this->curlDatas = $data;
        return $this;
    }

    /**
     * 设置请求头
     *
     * 与现有请求头合并（同名覆盖）。类默认请求头为：
     * - Accept: application/json, text/javascript 等
     * - Content-Type: application/json
     * 其中 Content-Type 会在 send() 中按请求类型强制覆盖。
     *
     * ```php
     * $curl = Curl::init()->url($url)->headers(['Authorization' => 'Bearer xxxx']);
     * ```
     *
     * @param array $headers 请求头集合（key 为请求头名，value 为值）
     * @return $this
     */
    public function headers($headers)
    {
        $this->curlHeaders = Arr::merge($this->curlHeaders, $headers);
        return $this;
    }

    /**
     * 设置 Cookie
     *
     * 与现有 Cookie 合并（同名覆盖）。发送时自动拼接为 "k1=v1; k2=v2"。
     *
     * ```php
     * $curl = Curl::init()->url($url)->cookie(['session_id' => 'abc123']);
     * ```
     *
     * @param array $cookies Cookie 集合
     * @return $this
     */
    public function cookie($cookies)
    {
        $this->curlCookie = Arr::merge($this->curlCookie, $cookies);
        return $this;
    }

    /**
     * 设置请求体是否为 JSON
     *
     * 控制请求体的序列化方式及 Content-Type：
     * - true（默认）：请求体 json_encode，Content-Type 为 application/json；
     * - false：请求体 http_build_query，Content-Type 为 application/x-www-form-urlencoded。
     * 文件上传时该开关会被自动忽略（强制 multipart/form-data）。
     *
     * ```php
     * // 发送表单格式请求体
     * $res = Curl::init()->url($url)->data(['k' => 'v'])->json(false)->post();
     * ```
     *
     * @param bool $isJson 是否 JSON 编码
     * @return $this
     */
    public function json($isJson = true)
    {
        $this->isJson = $isJson;
        return $this;
    }

    /**
     * 设置超时时间
     *
     * 设置整个请求（含连接与传输）的最长耗时，超过即中止并置为请求失败。
     *
     * @param int $timeout 超时秒数（默认 60）
     * @return $this
     */
    public function timeout($timeout = 60)
    {
        $this->curlTimeout = $timeout;
        return $this;
    }

    /**
     * 设置连接超时时间
     *
     * 仅限制建立 TCP/TLS 连接的耗时，传输阶段仍受 timeout() 控制。
     * 为 0 时不单独设置（走系统/curl 默认）。
     *
     * @param int $timeout 连接超时秒数，0 表示不单独设置
     * @return $this
     */
    public function connectTimeout($timeout = 0)
    {
        $this->curlConnectTimeout = $timeout;
        return $this;
    }

    /**
     * 设置 Referer
     *
     * 设置请求的 Referer 请求头（来源页面地址），部分服务会校验来源。
     *
     * @param string $referer 来源地址
     * @return $this
     */
    public function referer($referer)
    {
        $this->curlHeaders['Referer'] = $referer;
        return $this;
    }

    /**
     * 设置 User-Agent
     *
     * 设置请求的 User-Agent 请求头，用于标识客户端类型/版本。
     *
     * @param string $userAgent 用户代理字符串
     * @return $this
     */
    public function userAgent($userAgent)
    {
        $this->curlHeaders['User-Agent'] = $userAgent;
        return $this;
    }

    /**
     * 设置 Accept-Encoding
     *
     * 设置请求的 Accept-Encoding 请求头，声明支持的内容编码，如 gzip/deflate。
     * 注意：curl 在未显式设置时默认会自动解压 gzip 响应，设置后需自行处理解压。
     *
     * @param string $encoding 编码类型，如 "gzip, deflate"
     * @return $this
     */
    public function encoding($encoding = 'gzip, deflate')
    {
        $this->curlHeaders['Accept-Encoding'] = $encoding;
        return $this;
    }

    /**
     * 设置是否跟随重定向
     *
     * 是否自动跟随 3xx 重定向（CURLOPT_FOLLOWLOCATION）。默认关闭；
     * 若目标接口会 301/302 跳转，需开启以免拿到跳转页而非最终响应。
     *
     * @param bool $follow 是否跟随重定向（默认 true）
     * @return $this
     */
    public function followLocation($follow = true)
    {
        $this->curlOptions[CURLOPT_FOLLOWLOCATION] = $follow;
        return $this;
    }

    /**
     * 设置 HTTP Basic 认证
     *
     * 发送 `Authorization: Basic base64(user:pass)` 请求头，用于 HTTP Basic 认证。
     * 通过 CURLOPT_USERPWD + CURLAUTH_BASIC 实现（由 curl 自动 base64 编码）。
     *
     * ```php
     * $res = Curl::init()->url($url)->basicAuth('admin', 'secret')->get()->responseData();
     * ```
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @return $this
     */
    public function basicAuth($username, $password)
    {
        $this->curlOptions[CURLOPT_USERPWD] = $username . ':' . $password;
        $this->curlOptions[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        return $this;
    }

    /**
     * 设置代理
     *
     * 配置 HTTP 代理。传入选项与默认配置合并，可用键：
     * open / url / port / username / password。username/password 可空。
     *
     * ```php
     * $res = Curl::init()->url($url)
     *     ->proxy(['open' => true, 'url' => 'http://proxy.example.com', 'port' => '8080'])
     *     ->get()->responseData();
     * ```
     *
     * @param array $options 代理配置，至少包含 open 与 url
     * @return $this
     */
    public function proxy($options = [])
    {
        if ($options) {
            $this->proxy = Arr::merge($this->proxy, $options);
        }
        return $this;
    }

    /**
     * 设置 HTTPS 相关选项
     *
     * 控制是否校验服务端 SSL 证书。默认校验；仅当调试自签名/内部测试证书时
     * 才应传 false 跳过校验（生产环境切勿关闭，存在中间人攻击风险）。
     *
     * @param bool $verify 是否校验 SSL 证书（默认 true），false 表示跳过校验
     * @return $this
     */
    public function https($verify = true)
    {
        $this->bypassSslVerification = !$verify;
        return $this;
    }

    /**
     * 设置客户端 SSL 证书
     *
     * 为需要双向 TLS 认证（mTLS）的服务端设置客户端证书。
     * 需配合 sslKey() 使用（微信支付证书、企业内部 mTLS 接口等场景）。
     *
     * ```php
     * $res = Curl::init()->url($url)
     *     ->sslCert('/path/apiclient_cert.pem')
     *     ->sslKey('/path/apiclient_key.pem')
     *     ->post()->responseData();
     * ```
     *
     * @param string $filePath 客户端证书文件路径
     * @param string $type     证书类型（PEM/DER/ENG，默认 PEM）
     * @return $this
     */
    public function sslCert($filePath, $type = 'PEM')
    {
        $this->sslCertFilePath = $filePath;
        $this->sslCertType = $type;
        return $this;
    }

    /**
     * 设置客户端 SSL 密钥
     *
     * 配合 sslCert() 使用，提供客户端证书对应的私钥文件。
     *
     * @param string $filePath 密钥文件路径
     * @param string $type     密钥类型（PEM/DER/ENG，默认 PEM）
     * @return $this
     */
    public function sslKey($filePath, $type = 'PEM')
    {
        $this->sslKeyFilePath = $filePath;
        $this->sslKeyType = $type;
        return $this;
    }

    /**
     * 传递额外 cURL 选项
     *
     * 允许直接注入任意 cURL 选项，覆盖或补充内置默认值（优先级最高）。
     * 注意：CURLOPT_HEADER / CURLOPT_RETURNTRANSFER 等关键选项会被 send() 强制开启，
     * 自定义这些选项可能无法覆盖。
     *
     * ```php
     * $res = Curl::init()->url($url)
     *     ->options([CURLOPT_SSL_VERIFYPEER => false])
     *     ->get()->responseData();
     * ```
     *
     * @param array $options cURL 选项集合，key 为 CURLOPT_* 常量，value 为选项值
     * @return $this
     */
    public function options($options)
    {
        $this->curlOptions = Arr::merge($this->curlOptions, $options);
        return $this;
    }

    /**
     * 重置请求配置（保留句柄复用）
     *
     * 将 url/headers/data/method/timeout/cookie/ssl/代理/选项等全部恢复为初始状态，
     * 但**保留 cURL 句柄**以便复用连接。适合同一实例在循环中连续发起多次请求。
     *
     * ```php
     * $curl = new Curl();
     * foreach ($urls as $url) {
     *     $data = $curl->url($url)->get()->responseData();
     *     $curl->reset();   // 复用句柄，重置配置
     * }
     * ```
     *
     * @return $this
     */
    public function reset()
    {
        $this->requestUrl = null;
        $this->curlHeaders = [
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type' => 'application/json'
        ];
        $this->curlDatas = [];
        $this->curlMethod = 'get';
        $this->curlTimeout = 60;
        $this->curlConnectTimeout = 0;
        $this->isJson = true;
        $this->curlCookie = [];
        $this->uploadFile = [];
        $this->bypassSslVerification = false;
        $this->sslCertType = 'PEM';
        $this->sslCertFilePath = null;
        $this->sslKeyType = 'PEM';
        $this->sslKeyFilePath = null;
        $this->curlOptions = [];
        $this->proxy = [
            'open' => false,
            'url' => '',
            'port' => '',
            'username' => '',
            'password' => ''
        ];
        $this->responseData = null;
        $this->curlErrorMsg = null;
        $this->curlErrorNo = null;
        $this->responseHeadersData = null;
        $this->responseStatusCode = 200;
        return $this;
    }

    /**
     * 发送请求（由各请求方法自动调用，也可手动触发）
     *
     * 执行以下核心流程：
     * 1. 句柄存活检测：若被 close() 释放则重新 curl_init()，保证实例可复用；
     * 2. GET 请求体并入 URL 查询串；
     * 3. 判定是否为文件上传（method 为 file 或 uploadFile 非空），
     *    合并 CURLFile 并强制 multipart/form-data；
     * 4. 按请求类型强制 Content-Type；
     * 5. 收敛请求方法（POST/GET/HEAD/CONNECT/自定义方法）；
     * 6. 序列化请求体（JSON / http_build_query / 原样）；
     * 7. 合并 Cookie、代理、SSL 证书、自定义选项；
     * 8. curl_setopt_array + curl_exec，解析响应头与响应体。
     *
     * 成功时响应体按 Content-Type 或 isJson 自动尝试 json_decode；
     * 失败（curl 层错误）时只写 errorNo()/error()，responseData() 为 null。
     *
     * @return $this 便于继续链式读取响应
     */
    public function send()
    {
        // 句柄在构造时创建；若被 close() 释放，则重新创建以便复用实例
        if (!is_object($this->curlInstance)) {
            $this->curlInstance = curl_init();
        }
        if (!$this->requestUrl) {
            $this->curlErrorMsg = '请求地址未设置';
            $this->curlErrorNo = -1;
            $this->responseStatusCode = 0;
            $this->responseData = null;
            $this->responseHeadersData = null;
            return $this;
        }

        $curl = $this->curlInstance;
        $sendDatas = $this->curlDatas;
        $isJson = $this->isJson;

        if ($this->curlMethod === 'get' && $sendDatas) {
            $this->requestUrl = $this->appendQuery($this->requestUrl, $sendDatas);
            $sendDatas = [];
        }

        // 是否文件上传：method 为 file 或 uploadFile 非空
        $isUpload = ($this->curlMethod === 'file' || $this->uploadFile);
        if ($isUpload) {
            $this->isJson = false;
            $sendDatas = Arr::merge($sendDatas, $this->uploadFile);
        }

        $options = [
            CURLOPT_URL => $this->requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->curlTimeout,
            CURLOPT_SSL_VERIFYPEER => $this->bypassSslVerification ? false : true,
            CURLOPT_SSL_VERIFYHOST => $this->bypassSslVerification ? 0 : 2,
        ];

        if ($this->curlConnectTimeout > 0) {
            $options[CURLOPT_CONNECTTIMEOUT] = $this->curlConnectTimeout;
        }

        $headers = $this->curlHeaders;
        if (!isset($headers['Accept'])) {
            $headers['Accept'] = 'application/json, text/javascript, */*; q=0.01';
        }
        // 根据请求类型强制正确的 Content-Type（覆盖类默认遗留的 application/json），
        // 保证 Content-Type 与序列化方式一致：
        // 文件上传 → multipart/form-data；GET/HEAD → 无请求体，移除 Content-Type；
        // JSON 模式 → application/json；否则表单 → application/x-www-form-urlencoded
        if ($isUpload) {
            $headers['Content-Type'] = 'multipart/form-data';
        } elseif ($this->curlMethod === 'get' || $this->curlMethod === 'head') {
            unset($headers['Content-Type']);
        } elseif ($isJson) {
            $headers['Content-Type'] = 'application/json';
        } else {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        $options[CURLOPT_HTTPHEADER] = $this->buildHeaders($headers);

        // 请求方法收敛
        if ($isUpload || $this->curlMethod === 'post') {
            $options[CURLOPT_POST] = true;
        } elseif ($this->curlMethod === 'get') {
            $options[CURLOPT_HTTPGET] = true;
        } elseif ($this->curlMethod === 'head') {
            $options[CURLOPT_NOBODY] = true;
            $options[CURLOPT_CUSTOMREQUEST] = 'HEAD';
        } elseif ($this->curlMethod === 'connect') {
            $options[CURLOPT_CUSTOMREQUEST] = 'CONNECT';
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = strtoupper($this->curlMethod);
        }

        // 请求体
        if (!$isUpload && $this->curlMethod !== 'get' && $this->curlMethod !== 'head' && $sendDatas) {
            if ($isJson && Arr::isAssoc($sendDatas)) {
                $sendDatas = json_encode($sendDatas, JSON_UNESCAPED_UNICODE);
            } elseif (is_array($sendDatas)) {
                $sendDatas = http_build_query($sendDatas);
            }
            $options[CURLOPT_POSTFIELDS] = $sendDatas;
        } elseif ($isUpload && $sendDatas) {
            // 文件上传：POSTFIELDS 中的 CURLFile 自动触发 multipart/form-data
            $options[CURLOPT_POSTFIELDS] = $sendDatas;
        }

        // Cookie
        if ($this->curlCookie) {
            $options[CURLOPT_COOKIE] = $this->buildCookie($this->curlCookie);
        }

        // 代理
        if ($this->proxy['open'] && $this->proxy['url']) {
            $proxyUrl = $this->proxy['url'] . ':' . $this->proxy['port'];
            $options[CURLOPT_PROXY] = $proxyUrl;
            if ($this->proxy['username']) {
                $options[CURLOPT_PROXYUSERPWD] = $this->proxy['username'] . ':' . $this->proxy['password'];
            }
        }

        // SSL 证书/密钥
        if ($this->sslCertFilePath) {
            $options[CURLOPT_SSLCERTTYPE] = $this->sslCertType;
            $options[CURLOPT_SSLCERT] = $this->sslCertFilePath;
        }
        if ($this->sslKeyFilePath) {
            $options[CURLOPT_SSLKEYTYPE] = $this->sslKeyType;
            $options[CURLOPT_SSLKEY] = $this->sslKeyFilePath;
        }

        // 用户自定义选项覆盖默认值
        foreach ($this->curlOptions as $option => $value) {
            $options[$option] = $value;
        }

        $options[CURLOPT_HEADER] = true;

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);

        $this->responseHeadersData = null;
        $this->responseData = null;
        $this->responseStatusCode = 0;
        $this->curlErrorMsg = null;
        $this->curlErrorNo = null;

        if ($response === false) {
            $this->curlErrorMsg = curl_error($curl);
            $this->curlErrorNo = curl_errno($curl);
            return $this;
        }

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $rawHeader = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        $responseHeaders = [];
        if ($rawHeader) {
            $headerLines = explode("\r\n", $rawHeader);
            foreach ($headerLines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if ($key && $value) {
                        $responseHeaders[$key] = $value;
                    }
                }
            }
        }
        $this->responseHeadersData = $responseHeaders;
        $this->responseStatusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $contentType = isset($responseHeaders['Content-Type']) ? strtolower($responseHeaders['Content-Type']) : '';
        if ($this->isJson || strpos($contentType, 'application/json') !== false || strpos($contentType, 'text/json') !== false) {
            $decoded = json_decode($responseBody, true);
            $this->responseData = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $responseBody;
        } else {
            $this->responseData = $responseBody;
        }

        return $this;
    }

    /**
     * 暂停请求（挂起当前句柄）
     *
     * @param bool $isPause 是否暂停
     * @return bool
     */
    public function pause($isPause = true)
    {
        if (!is_object($this->curlInstance)) {
            return false;
        }
        return curl_pause($this->curlInstance, ($isPause ? CURLPAUSE_RECV : CURLPAUSE_CONT) | CURLPAUSE_SEND);
    }

    /**
     * 关闭并释放 cURL 句柄
     *
     * @return void
     */
    public function close()
    {
        if (is_object($this->curlInstance)) {
            curl_close($this->curlInstance);
        }
        $this->curlInstance = null;
    }

    /**
     * 获取 cURL 句柄
     *
     * 返回当前底层 cURL 句柄，可进行更底层的操作。PHP 8 下为 \CurlHandle 对象，
     * 句柄被 close() 后为 null。判句柄存活应使用 is_object()。
     *
     * @return \CurlHandle|\resource|null 底层 cURL 句柄
     */
    public function curlInstance()
    {
        return $this->curlInstance;
    }

    /**
     * 获取响应体
     *
     * 返回响应体内容。若响应 Content-Type 为 JSON（或 json() 开启），
     * 会自动 json_decode 为数组；解析失败时原样返回字符串。
     *
     * 注意：请求失败（curl 层错误）时返回 null，请结合 errorNo()/statusCode() 判断。
     *
     * @return mixed 响应体（JSON 自动解析为数组）
     */
    public function responseData()
    {
        return $this->responseData;
    }

    /**
     * 获取响应头
     *
     * 返回响应头关联数组（key 为头名，value 为值，均 trim 处理）。
     * 请求失败时为 null。
     *
     * ```php
     * $headers = $curl->responseHeaders();
     * $contentType = $headers['Content-Type'] ?? '';
     * ```
     *
     * @return array|null 响应头关联数组
     */
    public function responseHeaders()
    {
        return $this->responseHeadersData;
    }

    /**
     * 获取 HTTP 响应状态码
     *
     * 请求成功返回服务端状态码（如 200/404）；请求失败（未建立连接等）为 0。
     *
     * @return int HTTP 状态码
     */
    public function statusCode()
    {
        return $this->responseStatusCode;
    }

    /**
     * 获取 cURL 错误码
     *
     * 返回 curl_errno() 的错误码（如 CURLE_COULDNT_CONNECT），
     * 请求成功时为 null；URL 未设置时为 -1。
     *
     * @return int|null cURL 错误码
     */
    public function errorNo()
    {
        return $this->curlErrorNo;
    }

    /**
     * 获取 cURL 错误信息
     *
     * 返回 curl_error() 的错误描述，请求成功时为 null；URL 未设置时为
     * "请求地址未设置"。
     *
     * @return string|null cURL 错误信息
     */
    public function error()
    {
        return $this->curlErrorMsg;
    }

    /**
     * 获取响应数据（responseData 别名）
     *
     * 与 responseData() 等价，保留用于兼容旧调用方。
     *
     * @return mixed 响应体（JSON 自动解析为数组）
     */
    public function getData()
    {
        return $this->responseData;
    }

    public function __destruct()
    {
        $this->close();
    }
}
