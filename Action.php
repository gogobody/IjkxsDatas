<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class IjkxsDatas_Action extends Typecho_Widget implements Widget_Interface_Do
{
    //上传文件目录
    const UPLOAD_DIR = '/usr/uploads';
    /**
     * @var mixed|Typecho_Db|null
     */
    private $db;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
    }

    public function action()
    {
        include_once "Ijk_util.php";
        $this->db = Typecho_Db::get();
        $wpdb = $this->db;
        $reqData = $this->IjkdatasMergeRequest();

        $plugin_options = Typecho_Widget::widget('Widget_Options')->plugin('IjkxsDatas');
        if ($_GET["__ijk_flag"] == "post") {
            //输出测试日志
            //error_log('title:'.$reqData["title"].PHP_EOL,3,'/www/wwwroot/typecho.simpledatas.com/usr/plugins/KeyDatas/test.log');
            //error_log('ijk_password:'.$reqData['ijk_password'].PHP_EOL,3,'/www/wwwroot/typecho.simpledatas.com/usr/plugins/KeyDatas/test.log');
            $this->verifyPassword($reqData['ijk_password']);
            $title = $reqData["title"];
            $text = $reqData["text"];

            if (empty($title) || empty($text)) {
                keydatas_failRsp('1405', "title and content are empty", "文章标题与内容都不能为空");
            }

            $indexUrl = Helper::options()->siteUrl;

            //文章标题重复处理
            //$keydatas_title_unique = Typecho_Widget::widget('Widget_Options')->plugin('KeyDatas')->keydatas_title_unique;
            $keydatas_title_unique = $plugin_options->keydatas_title_unique;
            $use_markdown = $plugin_options->use_markdown;
            if (!is_null($keydatas_title_unique) && !empty($title)) {
                $post = $wpdb->fetchRow($wpdb->select()->from('table.contents')->where('title = ?', $title));
                if ($post) {
                    $postId = $post['cid'];
                    $relationships = $wpdb->fetchAll($wpdb->select()->from('table.relationships')->where('cid = ?', $postId));
                    $re = array_pop($relationships);
                    $cate = $wpdb->fetchRow($wpdb->select()->from('table.metas')->where('mid = ?', $re['mid']));
                    $lastCategory = $cate['name'];
                    $slug = $post['slug'];
                    $time = $post['created'];
                    $docUrl = $this->genDocUrl($indexUrl, $postId, $slug, $lastCategory, $time);
                    //这里可以补充图片
                    //downloadImages($_REQ);
//                    $_REQ = $this->IjkdatasMergeRequest();
//                    $loadImages = $this->downloadImages($_REQ);

                    keydatas_successRsp(array("url" => $docUrl . "?p={$postId}"),"相同标题文章已存在");

                }
            }

            //发布时间
            $created = Helper::options()->gmtTime;
            if (!empty($reqData["created"])) {
                $created = $reqData["created"];
                if (preg_match('/\d{10,13}/', $created)) {
                    $created = intval($created);
                } else {
                    $created = intval(strtotime($created));
                }
            }
            // 查找作者，如找不到，就新增作者
            $authorId = 1;
            $author = htmlspecialchars_decode($reqData["author"]);
            if (!empty($author)) {
                $existUid = $this->isUserExist($author);
                if (!empty($existUid)) {
                    $authorId = $existUid;
                } else {
                    $userId = $this->createUser($author);
                    if (!empty($userId)) {
                        $authorId = $userId;
                    }
                }
            }
            if (!is_null($use_markdown)){
                $text = empty($text) ? '<!--markdown-->' : '<!--markdown-->'.htmlspecialchars_decode($text);
            }else{
                $text = empty($text) ? '' : ''.htmlspecialchars_decode($text);
            }

            /////图片http下载，不能用_POST
            $_REQ = $this->IjkdatasMergeRequest();
            $loadImages = $this->downloadImages($_REQ);
            $replace_img = $plugin_options->replace_img;

            if (!is_null($replace_img)){

                foreach ($loadImages as $img){
                    if ($img[0] and $img[1]){
                        $text = str_replace($img[0],$img[1],$text);
                    }
                }
            }

            //插入到文章表相应字段中
            $insertContents = array(
                'title' => empty($title) ? '' : htmlspecialchars_decode($title),
                'created' => $created,
                'modified' => time(),
                'text' => $text,
                'order' => !isset($reqData['order']) ? 0 : intval($reqData['order']),
                'authorId' => $authorId,
                'template' => !isset($reqData['template']) ? NULL : $reqData['template'],
                'type' => !isset($reqData['type']) ? 'post' : $reqData['type'],
                'status' => !isset($reqData['status']) ? 'publish' : $reqData['status'],
                'password' => !isset($reqData['password']) ? NULL : $reqData['password'],
                'commentsNum' => 0,
                'allowComment' => !isset($reqData['allowComment']) ? '1' : $reqData['allowComment'],
                'allowPing' => !isset($reqData['allowPing']) ? '1' : $reqData['allowPing'],
                'allowFeed' => !isset($reqData['allowFeed']) ? '1' : $reqData['allowFeed'],
                'parent' => !isset($reqData['parent']) ? 0 : intval($reqData['parent'])
            );
            try {
                $postId = $wpdb->query($wpdb->insert('table.contents')->rows($insertContents));
            } catch (Exception $e) {
                keydatas_failRsp('1405', $e->getMessage(), "新增文章失败");
            }

            $slug = $postId;
            if ($postId > 0) {
//                $randSlug = Typecho_Common::randString(6);
//                $slug = empty($reqData['slug']) ? $randSlug : $reqData['slug'];
//                $slug = Typecho_Common::slugName($slug, $postId);
                $this->db->query($this->db->update('table.contents')->rows(array('slug' => $slug))
                    ->where('cid = ?', $postId));
                /** 保存自定义字段 */

                $this->applyFields($this->getFields(), $postId);
            } else {
                keydatas_failRsp('1405', "add document failed", "新增文章失败");
            }


            //文章分类处理
            $categories = $reqData["categories"];
            $lastCategory = "default";

            if (!empty($categories)) {

                $categories = str_replace("，", ",", $categories);//把中文逗号替换成英文逗号
                $cates = explode(',', $categories);

                if (is_array($cates)) {
                    $cates = array_unique($cates);

                    //获取所有的分类id和名称
                    $allCates = $this->getAllCates();

                    for ($c = 0; $c < count($cates); $c++) {
                        $lastCategory = $cates[$c];
                        //分类不存在则创建
                        $metaCate = $this->isCateExist($cates[$c], $allCates);
                        $cateId = $metaCate[0];
                        if (!$metaCate) {
                            $cateId = $wpdb->query($wpdb->insert('table.metas')
                                ->rows(array(
                                    'name' => $cates[$c],
                                    'slug' => Typecho_Common::slugName($cates[$c]),
                                    'type' => 'category',
                                    'count' => 1,
                                    'order' => 1,
                                    'parent' => 0
                                )));
                        } else {
                            //更新分类对应的文章数量
                            $update = $wpdb->update('table.metas')->rows(array('count' => ($metaCate[2] + 1)))->where('mid=?', $metaCate[0]);
                            $updateRows = $wpdb->query($update);
                        }
                        try {
                            //插入关联分类和文章
                            $wpdb->query($wpdb->insert('table.relationships')->rows(array('cid' => $postId, 'mid' => $cateId)));
                        } catch (Exception $e) {
                            keydatas_failRsp('1405', 'add category error', '新增文章分类错误');
                        }
                    }
                }

            } else {
                //当没有传入分类时，取得typecho系统初始化时的默认分类
                $defaultMid = 1;
                $lastrelation = $wpdb->query($wpdb->insert('table.relationships')
                    ->rows(array(
                        'cid' => $postId,
                        'mid' => $defaultMid
                    )));
            }


            //标签
            $reqTags = $reqData["tags"];
            $lastTag = "default";

            if (!empty($reqTags)) {
                $reqTags = str_replace("，", ",", $reqTags);//把中文逗号替换成英文逗号
                $tags = explode(',', $reqTags);

                if (is_array($tags)) {
                    $tags = array_unique($tags);
                    $allTags = $this->getAllTags();
                    for ($c = 0; $c < count($tags); $c++) {
                        $lastTag = $tags[$c];
                        $oneTag = $this->isTagExist($tags[$c], $allTags);
                        $tagId = $oneTag[0];
                        if (!$oneTag) {

                            $tagId = $wpdb->query($wpdb->insert('table.metas')
                                ->rows(array(
                                    'name' => $tags[$c],
                                    'slug' => Typecho_Common::slugName($tags[$c]),
                                    'type' => 'tag',
                                    'count' => 1,
                                    'order' => 1,
                                    'parent' => 0
                                )));
                        } else {
                            $update = $wpdb->update('table.metas')->rows(array('count' => ($oneTag[2] + 1)))->where('mid=?', $oneTag[0]);
                            $updateRows = $wpdb->query($update);
                        }

                        try {
                            $wpdb->query($wpdb->insert('table.relationships')->rows(array('cid' => $postId, 'mid' => $tagId)));
                        } catch (Exception $e) {
                            keydatas_failRsp('1405', 'add tag error', '新增文章标签错误');
                        }
                    }
                }

            }

            // tepass 处理
            $all = Typecho_Plugin::export();

            if (array_key_exists('TePass', $all['activated'])){
                $twidget = new Widget_Contents_Post_Edit(new Typecho_Request(),new Typecho_Response());
                if ($postId){
                    $this->db->fetchRow($this->db->select()->from('table.contents')
                        ->where('table.contents.type = ? OR table.contents.type = ?', 'post', 'post_draft')
                        ->where('table.contents.cid = ?', $postId)
                        ->limit(1), array($twidget, 'push'));
                    $twidget->pluginHandle()->finishPublish($insertContents, $twidget);
                }
            }

            $docUrl = $this->genDocUrl($indexUrl, $postId, $slug, $lastCategory, $insertContents['created']);
            keydatas_successRsp(array("url" => $docUrl),'发布成功');

        }
        elseif ($_GET["__ijk_flag"] == "category_list"){
            $cates = $this->db->fetchAll($this->db->select('name','mid')->from('table.metas')->where('type = ?','category'));
            //显示分类
            foreach ($cates as $f => $v) {
                echo "<<<".$v['mid'] . "==" . $v['name'].">>><br>";
            }
            exit();
        }

    }
    //..action end
    /**
     * 检查字段名是否符合要求
     *
     * @param string $name
     * @access public
     * @return boolean
     */
    public function checkFieldName($name)
    {
        return preg_match("/^[_a-z][_a-z0-9]*$/i", $name);
    }
    /**
     * 设置单个字段
     *
     * @param string $name
     * @param string $type
     * @param string $value
     * @param integer $cid
     * @access public
     * @return integer
     */
    public function setField($name, $type, $value, $cid)
    {
        if (empty($name) || !$this->checkFieldName($name)
            || !in_array($type, array('str', 'int', 'float'))) {
            return false;
        }

        $exist = $this->db->fetchRow($this->db->select('cid')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, $name));

        if (empty($exist)) {
            return $this->db->query($this->db->insert('table.fields')
                ->rows(array(
                    'cid'           =>  $cid,
                    'name'          =>  $name,
                    'type'          =>  $type,
                    'str_value'     =>  'str' == $type ? $value : NULL,
                    'int_value'     =>  'int' == $type ? intval($value) : 0,
                    'float_value'   =>  'float' == $type ? floatval($value) : 0
                )));
        } else {
            return $this->db->query($this->db->update('table.fields')
                ->rows(array(
                    'type'          =>  $type,
                    'str_value'     =>  'str' == $type ? $value : NULL,
                    'int_value'     =>  'int' == $type ? intval($value) : 0,
                    'float_value'   =>  'float' == $type ? floatval($value) : 0
                ))
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    /**
     * 保存自定义字段
     *
     * @param array $fields
     * @param mixed $cid
     * @access public
     * @return void
     */
    public function applyFields(array $fields, $cid)
    {
        $exists = array_flip(Typecho_Common::arrayFlatten($this->db->fetchAll($this->db->select('name')
            ->from('table.fields')->where('cid = ?', $cid)), 'name'));

        foreach ($fields as $name => $value) {
            $type = 'str';

            if (is_array($value) && 2 == count($value)) {
                $type = $value[0];
                $value = $value[1];
            } else if (strpos($name, ':') > 0) {
                list ($type, $name) = explode(':', $name, 2);
            }

            if (!$this->checkFieldName($name)) {
                continue;
            }

            if (isset($exists[$name])) {
                unset($exists[$name]);
            }

            $this->setField($name, $type, $value, $cid);
        }

        foreach ($exists as $name => $value) {
            $this->db->query($this->db->delete('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    /**
     * getFields
     *
     * @access protected
     * @return array
     */
    protected function getFields()
    {
        $fields = array();
        $fieldNames = $this->request->getArray('fieldNames');

        if (!empty($fieldNames)) {
            $data = array(
                'fieldNames'    =>  $this->request->getArray('fieldNames'),
                'fieldTypes'    =>  $this->request->getArray('fieldTypes'),
                'fieldValues'   =>  $this->request->getArray('fieldValues')
            );
            foreach ($data['fieldNames'] as $key => $val) {
                if (empty($val)) {
                    continue;
                }

                $fields[$val] = array($data['fieldTypes'][$key], $data['fieldValues'][$key]);
            }
        }

        $customFields = $this->request->getArray('fields');
        if (!empty($customFields)) {
            $fields = array_merge($fields, $customFields);
        }

        return $fields;
    }
    /**
     * 获取文件完整路径
     * @return string
     */
    public function getFilePath()
    {
        //typecho的方法取不到值？
        //$rootUrl=$this->options->siteUrl();
        //使用php的方法试试
        $rootUrl = dirname(dirname(dirname(dirname(__FILE__))));
        //error_log('rootUrl:'.$rootUrl.PHP_EOL,3,'/www/wwwroot/typecho.simpledatas.com/usr/plugins/KeyDatas/test.log');
        return $rootUrl . '/usr/uploads';
    }

    /**
     * 查找文件夹，如不存在就创建并授权
     * @return string
     */
    public function createFolders($dir)
    {
        return is_dir($dir) or ($this->createFolders(dirname($dir)) and mkdir($dir, 0777));
    }

    public function IjkdatasMergeRequest()
    {
        if (isset($_GET['__ijk_flag'])) {
            $_REQ = array_merge($_GET, $_POST);
        } else {
            $_REQ = $_POST;
        }
        return $_REQ;
    }


    ////图片http下载
    public function downloadImages($post,$url_title='')
    {
        $local_img_links = [];
        try {
            //error_log('kds_download_imgs_flag:'.$post['__ijk_download_imgs_flag'].PHP_EOL,3,'/www/wwwroot/typecho.simpledatas.com/usr/plugins/KeyDatas/test.log');
            //error_log('kds_docImgs:'.$post['__ijk_docImgs'].PHP_EOL,3,'/www/wwwroot/typecho.simpledatas.com/usr/plugins/KeyDatas/test.log');

            $downloadFlag = isset($post['__ijk_download_imgs_flag']) ? $post['__ijk_download_imgs_flag'] : '';
            if (!empty($downloadFlag) && $downloadFlag == "true") {
                $docImgsStr = isset($post['__ijk_docImgs']) ? $post['__ijk_docImgs'] : '';

                if (!empty($docImgsStr)) {
                    $docImgs = explode(',', $docImgsStr);
                    if (is_array($docImgs)) {
//                        $uploadDir = $this->getFilePath();
                        $cnt= 0;
                        foreach ($docImgs as $imgUrl) {
                            $date = new Typecho_Date();
                            $endfix = '/' . $date->year . '/' . $date->month;
                            $uploadDirFix = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR;
                            $path = Typecho_Common::url($uploadDirFix,
                                    defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__)
                                . $endfix;
                            $newSiteUrl = substr(Helper::options()->siteUrl,0,strlen(Helper::options()->siteUrl)-1);// 去掉最后一个 /
                            $local_link = $newSiteUrl.$uploadDirFix.$endfix;
                            $this->createFolders($path);

                            $mimes=array(
                                'image/bmp'=>'bmp',
                                'image/gif'=>'gif',
                                'image/jpeg'=>'jpg',
                                'image/png'=>'png',
                                'image/x-icon'=>'ico'
                            );
                            if(($headers=get_headers($imgUrl, 1))!==false){
                                // 获取响应的类型
                                $type=$headers['Content-Type'];
                            }
                            if ($type){
                                $ext = $mimes[$type];
                            }else{
                                $ext = 'jpg';
                            }
                            $imgName = md5($post["title"]).'_'.$cnt; // 默认用时间戳命名
                            $file = $path . '/' . $imgName.'.'.$ext; // 命名 md5 title + 图片序号
                            $local_link = $local_link. '/' . $imgName.'.'.$ext;
                            if (!file_exists($file)) {
                                $doc_image_data = file_get_contents($imgUrl);
                                $ret = file_put_contents($file, $doc_image_data);
                                if ($ret){
                                    array_push($local_img_links,[$imgUrl,$local_link]);
                                    $cnt = $cnt + 1;
                                }
                            }

                        }
                    }
                }
            }
        } catch (Exception $ex) {
            //error_log('error:'.$e->getMessage().PHP_EOL,3,'/www/wwwroot/typecho.simpledatas.com/usr/plugins/KeyDatas/test.log');
        }
        return $local_img_links;
    }


    //取得所有的分类
    public function getAllCates()
    {
        $categories = null;
        $this->widget('Widget_Metas_Category_List')->to($categories);
        $categoriesArr = array();
        if ($categories->have()) {
            $next = $categories->next();
            while ($next) {
                $mid = $next['mid'];
                $catename = $next['name'];
                $count = $next['count'];
                $parent = $next['parent'];
                array_push($categoriesArr, array($mid, $catename, $count, $parent));
                $next = $categories->next();
            }
        }
        return $categoriesArr;
    }

    //取得所有的标签
    public function getAllTags()
    {
        $tags = null;
        Typecho_Widget::widget('Widget_Metas_Tag_Admin')->to($tags);
        $tagsArr = array();
        while ($tags->next()) {
            array_push($tagsArr, array($tags->mid, $tags->name, $tags->count));
        }
        return $tagsArr;
    }

    //通过分类名判断分类是否存在
    public function isCateExist($cate, $allCates)
    {
        foreach ($allCates as $m) {
            if ($m[1] == $cate) {
                return $m;
            }
        }
        return false;
    }

    //通过标签名判断标签是否存在
    public function isTagExist($tag, $allTags)
    {
        foreach ($allTags as $t) {
            if ($t[1] == $tag) {
                return $t;
            }
        }
        return false;
    }

    //查找或创建创建用户
    public function createUser($author)
    {
        $existUid = $this->isUserExist($author);
        if (!$existUid) {
            $hasher = new PasswordHash(8, true);
            $randString6 = Typecho_Common::randString(6);
            $user = array(
                'name' => $author,
                'url' => '',
                'group' => 'contributor',
                'created' => $this->options->gmtTime,
                'password' => $randString6
            );
            $user['screenName'] = empty($user['screenName']) ? $user['name'] : $user['screenName'];
            $user['password'] = $hasher->HashPassword($user['password']);
            $authCode = function_exists('openssl_random_pseudo_bytes') ? bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Typecho_Common::randString(20));
            $user['authCode'] = $authCode;

            try {
                $insertId = $this->db->query($this->db->insert('table.users')->rows($user));
            } catch (Exception $e) {
                keydatas_failRsp('1406', "add user failed", "新增用户失败");
            }
            if ($insertId) {
                return $insertId;
            }
        } else {
            return $existUid;
        }
        return false;
    }

    //判断用户名是否存在
    public function isUserExist($author)
    {
        //先用作者名查找是否已存在用户
        $user = $this->db->fetchRow($this->db->select('uid')->from('table.users')->where('name = ?', $author)->limit(1));
        $uid = $user["uid"];
        if ($uid) {
            return $uid;
        } else {
            //如找不到的话，使用uid再找找看
            $user = $this->db->fetchRow($this->db->select('uid')->from('table.users')->where('uid = ?', $author)->limit(1));
            $uid = $user["uid"];
            if ($uid) {
                return $uid;
            } else {
                return false;
            }
        }
    }

    //生成需要返回的URL
    public function genDocUrl($indexUrl, $cid, $slug, $category, $time)
    {
        $today = date("Y-m-d", $time);
        $todayArr = explode('-', $today);
        $year = $todayArr[0];
        $month = $todayArr[1];
        $day = $todayArr[2];
        $rule = Typecho_Widget::widget('Widget_Options')->routingTable['post']['url'];
        $rule = preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $rule);
        $partUrl = str_replace(array('{cid}', '{slug}', '{category}', '{directory}', '{year}', '{month}', '{day}', '{mid}'), array($cid, $slug, $category, '[directory:split:0]', $year, $month, $day, 1), $rule);
        $partUrl = ltrim($partUrl, '/');
        $siteurl = $indexUrl;
        if (!Typecho_Widget::widget('Widget_Options')->rewrite) {
            $siteurl = $siteurl . 'index.php/';
        }
        $docUrl = $siteurl . $partUrl;
        return $docUrl;
    }


    public function verifyPassword($ijk_password)
    {
        //$this->options = Typecho_Widget::widget('Widget_Options')->plugin('KeyDatas');
        $this->options = Typecho_Widget::widget('Widget_Options')->plugin('IjkxsDatas');
        if (empty($ijk_password) || $ijk_password != $this->options->ijk_password) {
            keydatas_failRsp('1403', "wrong password", "发布密码错误");
        }
    }


}