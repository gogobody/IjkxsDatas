# IjkxsDatas
一款 typecho 采集辅助插件

![](https://cdn.jsdelivr.net/gh/gogobody/blog-img/blogimg/%E6%9C%AA%E5%91%BD%E5%90%8D%E7%9A%84%E8%AE%BE%E8%AE%A1.png)

IjkxsDatas 插件为 typecho 采集辅助插件，支持免登录发布文章，图片下载等功能。
![](https://cdn.jsdelivr.net/gh/gogobody/blog-img/blogimg/20210212171133.png)

## 使用教程
1. 插件后台的发布地址为API地址，例如图上：http://localhost/action/ijkxs-datas，密码为个人设置的接口调用通行证。
2. Markdown 前缀功能使得采集的文章会被标记为Markdown 格式
3. 本地图片替换，将采集的图片下载到本地，并将文章内容图片链接替换为本地链接。

## API设计

### 接口地址：

见插件后台，如
```
http://localhost/action/ijkxs-datas
```
### 获取分类接口：
```
接口地址+?__ijk_flag=category_list
```
输出格式：
```
<<<[分类ID]==[分类名称]>>>
```
如：
```
<<<1==默认分类1>>>
<<<2==默认分类2>>>
```
### 文章发布接口
```
接口地址+?__ijk_flag=post&ijk_password=xxx插件后台配置的
```
POST参数：
|  名称   | 值  | 是否必填|
|  ----  | ----  | ----  |
| categories  | 分类名称，多个可,分隔，不存在会自动创建 |是|
| title  | 标题 |是|
| text| 内容 |是|
| tag| 标签，多个可,分隔，不存在会自动创建|否|
| created| 创建时间戳|否|
| __ijk_download_imgs_flag  | 是否下载图片 |否|
| __ijk_docImgs  | 图片链接，多个链接用,分隔|否|
| order| 顺序|否|
| author| 作者名字，可以为空，则选择管理员|否|
| type| 类型，不填默认post|否|
| status| 状态，见typecho 文档，是否公开|否|
| password| 是否有密码|否|
| allowComment| 默认1|否|
| allowPing| 默认1|否|
| allowFeed| 默认1|否|


