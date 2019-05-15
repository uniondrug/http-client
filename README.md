# HttpClient component for uniondrug/framework

> Uniondrug微服务Http客户端的封装，加入了Trace功能。


## 安装

```shell
$ cd project-home
$ composer require uniondrug/http-client
```

修改 `app.php` 配置文件，导入服务。服务名称：`httpClient`。

```php
return [
    'default' => [
        ......
        'providers'           => [
            ......
            \Uniondrug\HttpClient\HttpClientServiceProvider::class,
        ],
    ],
];
```

## 使用

```php
    // 在 Injectable 继承下来的对象中：
    $data = $this->getDI()->getShared('httpClient')->get($url);

    // 或者 直接使用 属性方式
    $data = $this->httpClient->get($url)
```
