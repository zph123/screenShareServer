##简介
实现基于webrtc的一对一屏幕分享项目

体验网址：
https://www.zphteach.com

搭建要求：

1. https的域名访问（购买域名、申请ssl证书）
`为什么：浏览器中获取屏幕权限需要https，否则浏览器禁止授权，因为要分享设备A的屏幕到设备B，即使是本地调试也不建议用localhost方式，本地调试就把买的域名指向局域网IP就行。
https://wanwang.aliyun.com/domain/购买个域名一年几十元。证书就是用免费的就行https://help.aliyun.com/document_detail/148895.html?spm=5176.10695662.1996646101.searchclickresult.65354131wFwYpI`

2. websocket信令服务器（基于PHP的Swoole）
`为什么：webrtc在完成连接前，需要信令服务器交换webrtc客户端的信息。PHP的Swoole扩展可直接开发原生Websocket服务`

3. webrtc客户端
    * web版（基于node的Vue CLI）
    `为什么：单页面开发效率低，Vue CLI方便快速成型前端页面`
    * android版（Android studio编译）

4. coturn（局域网测试可跳过）
`为什么：解决在非局域网中，webrtc通讯的问题。参考网址：https://github.com/coturn/coturn、https://www.jianshu.com/p/915eab39476d`

备注：

* 优点：
     1. 代码成型，自行搭建，直接修改相关的配置参数即可
     2. 代码部分不复杂，无论是websocket服务端还是webrtc客户端都是用原生语法的代码写的，方便更改代码，适合入门级，弄清楚webrtc以及websocket交互的原理
     3. php的websocket服务放到了Laravel框架中，方便后续进行curd
     4. webrtc的web代码通过vue cli开发，页面布局组件使用的vant（https://youzan.github.io/vant/#/zh-CN/），起到了快速开发前端页面的好处。
        
* 缺点：
     1. 1v1的屏幕共享，不支持多人，原因是没有采用sfu等模式
     2. 虽然用的原生语法，但是代码放到框架里，如果要改代码，需要简单了解框架的功能和使用。
     3. 使用了两种语言php和js

## 安装说明
基于webrtc的一对一屏幕分享项目的websocket server部分

1. 首先安装PHP（不要再windows中测试，swoole不支持windows环境）

    PHP安装方式很多，比如：lnmp一键安装（https://lnmp.org/install.html）等

2. 安装PHP的Swoole扩展(openssl要编译进去)

    `https://wiki.swoole.com/#/environment`

    查看swoole版本（重点看openssl是否编译）
    
    `php --ri swoole` 
    
3. 下载代码

    `git clone xxxxx`
    
4. 进入项目

    `cd xxxxx`   
    
5. 下载依赖（https://developer.aliyun.com/composer）

    `composer install`
   
6. 复制配置文件

    `cp .env.example .env` 

7. 更改配置文件.env
    ```
   #redis配置
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   #websocket服务端口，对外服务采用的端口
   WEBSOCKET_PORT=9501
   #ssl文件，可申请免费的证书
   SSL_CERT_FILE = /data/sslcert/3800167_www.xxx.com.pem
   SSL_KEY_FILE = /data/sslcert/3800167_www.xxx.com.key
    ```
 
8. 安装redis，存储数据用（https://redis.io/download）
 
9. 启动进程websocket服务
 
    输入
    `php artisan swoole:websocket`
    看到
    `string(13) "WebrtcService"`
    说明启动成功
    
10. 打开http://www.websocket-test.com/，测试wss服务
    
    断开默认连接，输入`wss://www.xxx.com:9501/`点击连接，页面会提示`Websocket连接已建立，正在等待数据...`
    回到命令行终端看到`string(5) "getFd" fd2进入房间`说明服务提供有效

server部分运行好之后去运行client部分
* 基于webrtc的一对一屏幕分享项目的web client部分

    访问：https://github.com/zph123/screenShareWebClient
    
* 基于webrtc的一对一屏幕分享项目的android client部分

    访问：(整理好之后会放到github上)
