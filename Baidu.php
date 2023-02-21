<?php

use Typecho\Http\Client;


class Baidu
{
    public static function logger($data)
    {
        $log_dir = __TYPECHO_ROOT_DIR__ . '/usr/log/';
        if (!is_dir($log_dir)) @mkdir($log_dir, 0777);//检测缓存目录是否存在，自动创建
        $api_token = '1';
        $options = \Utils\Helper::options()->plugin('IjkxsDatas');

        $push_url = $options->baidu_push_url;
        if ($api_token == '1' and !empty($push_url)){
            //将日志记录写入文件中
            $row = date('Y-m-d H:i:s') . "\t" . $data['subject'] . "\t" . $data['action'] . "\t" . $data['object'] . "\t" . $data['result'] ."\t" . $data['json'] . "\t". $data['remain']  . "\r\n";
            @error_log($row, 3, $log_dir . date('Ymd') . '.log');
        }

    }
    /**
     * @throws Exception
     */
    public static function push($url)
    {
        $options = \Utils\Helper::options()->plugin('IjkxsDatas');

        $push_url = $options->baidu_push_url;
        //判断是否配置好API
        if (is_null($push_url)) {
//            throw new Exception('百度推送未配置');
            return;
        }

        //准备数据
        if (is_array($url)) {
            $urls = $url;
        } else {
            $urls = array($url);
        }

        //日志信息
        $log['subject'] = '我';
        $log['action'] = '百度收录API推送';
        $log['object'] = implode(",", $urls);

        try {
            //为了保证成功调用，老高先做了判断
            if (!Client::get()) {
                throw new \Typecho\Plugin\Exception(_t('对不起, 您的主机不支持 php-curl 扩展而且没有打开 allow_url_fopen 功能, 无法正常使用此功能'));
            }

            //发送请求
            $http = Client::get();
            $http->setData(implode("\n", $urls));
            $http->setHeader('Content-Type', 'text/plain');
            $http->send($push_url);
            $json = $http->getResponseBody();
            $return = json_decode($json, 1);
            $log['json'] = 'code:' . $return['error'] . ',msg:' . $return['message'];
            if (isset($return['error'])) {
                $log['result'] = '失败';
                $log['remain'] = '剩余条数获取失败';
            } else {
                $log['result'] = '成功';
                $log['remain'] = $return['remain'];
            }
        } catch (\Typecho\Exception $e) {
            $log['result'] = '失败：' . $e->getMessage();
        }

        self::logger($log);
    }


}
