<?php

/**
 * 即刻采集是一个免登陆采集辅助插件，配合其他采集软件使用，如火车头等等。。。
 * @package 即刻采集辅助插件
 * @author gogobody
 * @version 1.0.0
 * @link http://ijkxs.com/
 *
 */
require_once(__DIR__ . DIRECTORY_SEPARATOR . "Action.php");
class IjkxsDatas_Plugin implements Typecho_Plugin_Interface
{

    public static function activate()
    {
//        Typecho_Plugin::factory('index.php')->begin = array('IjkxsDatas_Plugin', 'post');
        Helper::addAction('ijkxs-datas', 'IjkxsDatas_Action');
    }

    public static function deactivate()
    {
        Helper::removeAction("ijkxs-datas");
    }

    public static function post()
    {
        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, "?__ijk_flag=") !== false) {
            require_once(__DIR__ . "/Action.php");
            $kdsTypecho = @new IjkxsDatas_Action(Typecho_Request::getInstance(), Typecho_Response::getInstance());
            $kdsTypecho->action();
        }
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $siteUrl = Helper::options()->siteUrl;
        $publishUrlLabel = new Typecho_Widget_Helper_Layout("label", array(
            'class' => 'typecho-label',
            'style' => 'margin-top:20px;'
        ));
        $publishUrlLabel->html('网站发布地址为：');
        $publishUrl = new Typecho_Widget_Helper_Layout("input",
            array(
                "disabled" => true,
                "readOnly" => true,
                "value" => $siteUrl.'action/ijkxs-datas',
                'type' => 'text',
                'class' => 'text',
                'style' => "width:80%;height:80%;"
            )
        );

        $rootDiv = new Typecho_Widget_Helper_Layout();
        $urldiv = new Typecho_Widget_Helper_Layout();
        $urldiv->setAttribute('class', 'typecho-option');
        $publishUrlLabel->appendTo($urldiv);
        $publishUrl->appendTo($urldiv);
        $form->addItem($urldiv);

        $ijk_password = new Typecho_Widget_Helper_Form_Element_Text('ijk_password', null, 'ijkxs.com', _t('发布密码：'), "（请注意修改并保管好,控制台发布需要用到）");

        // 文章标题去重选项
        $duplicateOptions = array(
            'no_keydatas_title_unique' => _t('根据标题去重，如存在相同标题，则不插入')
        );
        $duplicateOptionsValue = array('no_keydatas_title_unique');
        $keydatas_title_unique = new Typecho_Widget_Helper_Form_Element_Checkbox('keydatas_title_unique', $duplicateOptions,
            $duplicateOptionsValue, _t('标题去重:'));

        $form->addInput($ijk_password);
        $form->addInput($keydatas_title_unique->multiMode());

        $use_markdown = new Typecho_Widget_Helper_Form_Element_Checkbox('use_markdown', ['use_markdown'=>'添加Markdown前缀（添加之后文章会以Markdown解析）'],
            ['use_markdown'], _t('Markdown前缀:'));
        $form->addInput($use_markdown);

        $replace_img = new Typecho_Widget_Helper_Form_Element_Checkbox('replace_img', ['replace_img'=>'下载图片后，是否替换内容图片为本地链接'],
            ['replace_img'], _t('本地图片替换:'));
        $form->addInput($replace_img);

        $helperLayout = new Typecho_Widget_Helper_Layout();
        $itemOne_p = new Typecho_Widget_Helper_Layout('span', array(
            'style' => "floal:left;display:block;clear:left;margin-top:10px;"
        ));
        $itemOne_p->html("简介和使用教程：");
        $itemOne_ul = new Typecho_Widget_Helper_Layout('ul');
        $itemOne_ul->setAttribute('class', 'typecho-option');
        $itemOne_li1 = new Typecho_Widget_Helper_Layout('li');
        $descText = '即刻采集辅助插件是一个专为 typecho 打造的文章采集辅助插件，可配合其他采集工具使用<br>
更新地址  <a href="https://ijkxs.com/archives/165.html" target="_blank">  即刻学术</a> &nbsp;&nbsp;&nbsp;&nbsp;QQ交流群：1044509220</br>github : <a href="https://github.com/gogobody/IjkxsDatas.git">地址</a>';
        $itemOne_li1->setAttribute('class', 'description')->html($descText);
        $itemOne_ul->addItem($itemOne_li1);
        $helperLayout->addItem($itemOne_p);
        $helperLayout->addItem($itemOne_ul);

        $form->addItem($helperLayout);
        $form->appendTo($rootDiv);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

}
