<?php
/**
 * vrich_luckybox模块微站定义.
 *
 * @author vrich
 * @url
 */
defined('IN_IA') or exit('Access Denied');
define('ROOT_PATH', IA_ROOT.'/addons/vrich_luckybox');
class Vrich_luckyboxModuleSite extends WeModuleSite
{
    public function doMobileIndex()
    {
        //这个操作被定义用来呈现 功能封面
        global $_W, $_GPC;
        //获得微信用户信息
        $adminId = $_W['uniacid'];
        $userInfo = mc_oauth_userinfo($adminId);
        //商品类型列表（上架）
        $classList = pdo_getall('luckybox_goods_class', array('admin_id' => $adminId, 'status' => 1));
        //商品类型总数量
        $classNum = count($classList);
        //订单页面链接
        $orderUrl = $this->createMobileUrl('orderList');
        //ajax商品列表链接
        $goodsListUrl = $this->createMobileUrl('goodsList');
        //静态资源目录地址
        $resRoot = $_W['siteroot'].'/addons/vrich_luckybox/static';
        // 商品详细地址
        $goodsInfoUrl = $this->createMobileUrl('info');
        // 微信公众号ID
        $webSet = pdo_get('luckybox_web', ['admin_id' => $adminId]);
        //微信分享
        $account_api = WeAccount::create();
        $signPackage = $account_api->getJssdkConfig();
        include_once ROOT_PATH.DIRECTORY_SEPARATOR.'utils'.DIRECTORY_SEPARATOR.'loadUtil.php';
        loadUtilLibrary('Task');
        $taskClass = new Task($adminId);
        $taskClass->processPayRecord();
        include $this->template('index');
    }

    /**
     * 获得商品列表.
     */
    public function doMobileGoodsList()
    {
        global $_W, $_GPC;
        if ($_W['ispost']) {
            $adminId = $_W['uniacid'];
            // 默认展示数据列表
            $pageSize = 20;
            $pageIndex = max($_GPC['page'], 1);
            $condition = ['admin_id' => $adminId, 'sell_status' => 1];
            if (!empty($_GPC['class_id'])) {
                $condition['class_id'] = $_GPC['class_id'];
            }
            $list = $this->_showGoodsList($condition, $pageIndex, $pageSize);
            message(
                ['code' => '0000', 'msg' => '成功！', 'data' => $list['data']], '', 'ajax'
            );
        } else {
            message(['code' => 400012, 'msg' => '无效的数据访问！'], '', 'ajax');
        }
    }

    /**
     * 商品详细.
     */
    public function doMobileInfo()
    {
        global $_W, $_GPC;
        $adminId = $_W['uniacid'];
        // 判断是否有商品ID信息
        if (empty($_GPC['gId'])) {
            message('商品ID错误！', 'referer');
        }
        include_once ROOT_PATH.DIRECTORY_SEPARATOR.'utils'.DIRECTORY_SEPARATOR.'loadUtil.php';
        //获得微信用户信息
        $userInfo = mc_oauth_userinfo();
        $userId = $userInfo['openid'];
        loadUtilLibrary('Goods');
        $goodsClass = new Goods($_GPC['gId'], $adminId);
        // 商品详细
        $goodsData = $goodsClass->goodsData();
        if (empty($goodsData)) {
            message('错误的商品信息', 'referer');
        } elseif ($goodsData['sell_status'] != 1) {
            message('商品未上架或已下架', 'referer');
        }
        // 商品详细
        $goodsInfoData = $goodsClass->infoData();
        loadUtilLibrary('GoodsType');
        $goodsTypeClass = new GoodsType($goodsClass);
        $goodsTypeData = $goodsTypeClass->typeData();
        //静态资源目录地址
        $resRoot = $_W['siteroot'].'addons/vrich_luckybox/static';
        // 默认收货地址
        loadUtilLibrary('Address');
        $adrClass = new Address($userId);
        $defaultAdr = $adrClass->defaultAddress();
        // ajax获取所有的收货地址
        $ajaxAdrUrl = $this->createMobileUrl('ajaxAddress');
        // 地址调整地址
        $ajaxAdrSaveUrl = $this->createMobileUrl('ajaxAddressSave');
        // 微信公众号ID
        $webSet = pdo_get('luckybox_web', ['admin_id' => $adminId]);
        //微信分享
        $account_api = WeAccount::create();
        $signPackage = $account_api->getJssdkConfig();
        include $this->template('choose');
    }

    /**
     * 支付购买.
     */
    public function doMobileAjaxPay()
    {
        global $_W, $_GPC;
        $adminId = $_W['uniacid'];
        include_once ROOT_PATH.DIRECTORY_SEPARATOR.'utils'.DIRECTORY_SEPARATOR.'loadUtil.php';
        //获得微信用户信息
        $userInfo = mc_oauth_userinfo();
        $userId = $userInfo['openid'];
        $goodsId = intval($_GPC['gId']) ? intval($_GPC['gId']) : 0;
        $addressId = intval($_GPC['aId']) ? intval($_GPC['aId']) : 0;
        $buyNum = intval($_GPC['bNum']) ? intval($_GPC['bNum']) : 1;
        if (0 == $goodsId || 0 == $addressId) {
            message(['code' => 500413, 'msg' => '订单信息错误'], '', 'ajax');
        }
        // 收货地址
        loadUtilLibrary('Address');
        $adrClass = new Address($userId);
        $selectAdr = $adrClass->getAddressInfo($addressId);
        if (empty($selectAdr) || $selectAdr['user_id'] != $userId) {
            message(['code' => 500511, 'msg' => '收货地址信息错误'], '', 'ajax');
        }
        //商品
        loadUtilLibrary('Goods');
        $goodsClass = new Goods($_GPC['gId'], $adminId);
        // 商品详细
        $goodsData = $goodsClass->goodsData();
        if (empty($goodsData)) {
            message(['code' => 500512, 'msg' => '错误的商品信息'], '', 'ajax');
        } elseif ($goodsData['sell_status'] != 1) {
            message(['code' => 500513, 'msg' => '商品未上架或已下架'], '', 'ajax');
        }
        //商品剩余数量
        $goodsSurplusNum = $goodsData['num'] - $goodsData['sell_num'];
        if ($goodsSurplusNum < 1) {
            message(['code' => 500514, 'msg' => '商品已售完了'], '', 'ajax');
        }
        loadUtilLibrary('GoodsType');
        $goodsTypeClass = new GoodsType($goodsClass);
        $goodsTypeData = $goodsTypeClass->typeData();
        //商品种类数量
        $goodsTypeNum = count($goodsTypeData);
        if ($buyNum != 1 && $buyNum != $goodsTypeNum) {
            message(['code' => 500515, 'msg' => '购买数量错误'], '', 'ajax');
        } elseif ($goodsSurplusNum < $buyNum) {
            message(['code' => 500516, 'msg' => '商品库存不足,请重新数量'], '', 'ajax');
        }
        //更新出售商品数量
        $sellResult = $goodsClass->sellNum($buyNum);
        if (!$sellResult) {
            message(['code' => 500517, 'msg' => '商品库存更新失败'], '', 'ajax');
        }
        loadUtilLibrary('Order');
        $orderClass = new Order($userId, $adminId);
        $addOrderData = array(
            'order_code' => $this->_getNumOrder(),
            'goods_id' => $goodsId,
            'num' => $buyNum,
            'price' => ($goodsData['price'] / 100) * $buyNum,
            'adr_name' => $selectAdr['name'],
            'adr_phone' => $selectAdr['phone'],
            'adr_info' => $selectAdr['address'],
        );
        $result = $orderClass->addNew($addOrderData);
        if (!$result) {
            $goodsClass->revertSellNum($buyNum);
            message(['code' => 500518, 'msg' => '订单生成失败'], '', 'ajax');
        }
        //构造支付请求中的参数
        $params = array(
            'tid' => $addOrderData['order_code'],      //充值模块中的订单号，此号码用于业务模块中区分订单，交易的识别码
            'ordersn' => $addOrderData['order_code'],  //收银台中显示的订单号
            'title' => $goodsData['name'],          //收银台中显示的标题
            'fee' => $addOrderData['price'],      //收银台中显示需要支付的金额,只能大于 0
        );
        message(['code' => '0000', 'msg' => 'success', 'data' => $params], '', 'ajax');
    }

    /**
     * 支付回调.
     *
     * @param $params
     */
    public function payResult($params)
    {
        global $_W;
        load()->func('logging');
        logging_run($params, 'wechat', 'luckybox');
        //根据参数params中的result来判断支付是否成功
        if ($params['result'] == 'success' && $params['from'] == 'notify') {
            //此处会处理一些支付成功的业务代码
            $adminId = $_W['uniacid'];
            $userId = $params['user'];
            include_once ROOT_PATH.DIRECTORY_SEPARATOR.'utils'.DIRECTORY_SEPARATOR.'loadUtil.php';
            loadUtilLibrary('Order');
            $orderClass = new Order($userId, $adminId);
            $orderInfo = $orderClass->getOrderInfoByOrderCode($params['tid']);
            if (!empty($orderInfo)) {
                $orderClass->updateOrderStatus($orderInfo['id'], 1);
            }
        }
        //因为支付完成通知有两种方式 notify，return,notify为后台通知,return为前台通知，需要给用户展示提示信息
        //return做为通知是不稳定的，用户很可能直接关闭页面，所以状态变更以notify为准
        //如果消息是用户直接返回（非通知），则提示一个付款成功
        //如果是JS版的支付此处的跳转则没有意义
        if ($params['from'] == 'return') {
            if ($params['result'] == 'success') {
                message('支付成功！', '../../app/'.url('mc/home'), 'success');
            } else {
                message('支付失败！', '../../app/'.url('mc/home'), 'error');
            }
        }
    }

    /**
     * 异步拉取用户收货地址
     */
    public function doMobileAjaxAddress()
    {
        global $_W, $_GPC;
        //获得微信用户信息
        $userInfo = mc_oauth_userinfo();
        $userId = $userInfo['openid'];
        include_once ROOT_PATH.DIRECTORY_SEPARATOR.'utils'.DIRECTORY_SEPARATOR.'loadUtil.php';
        loadUtilLibrary('Address');
        $adrClass = new Address($userId);
        $data = $adrClass->addressData();
        message(['code' => '0000', 'msg' => 'success', 'data' => $data], '', 'ajax');
    }

    /**
     * 收货地址处理.
     */
    public function doMobileAjaxAddressSave()
    {
        global $_W, $_GPC;
        if ($_W['ispost']) {
            if (!isset($_GPC['id']) || intval($_GPC['id']) < 0) {
                message(['code' => '500405', 'msg' => '错误的请求'], '', 'ajax');
            }
            if (empty($_GPC['name']) || empty($_GPC['phone']) || empty($_GPC['info'])) {
                message(['code' => '500406', 'msg' => '信息请全部填写完整'], '', 'ajax');
            }
            //获得微信用户信息
            $userInfo = mc_oauth_userinfo();
            $userId = $userInfo['openid'];
            include_once ROOT_PATH.DIRECTORY_SEPARATOR.'utils'.DIRECTORY_SEPARATOR.'loadUtil.php';
            loadUtilLibrary('Address');
            $adrClass = new Address($userId);
            if (empty($_GPC['id'])) {
                // 添加收货地址
                $result = $adrClass->addNews($_GPC['name'], $_GPC['phone'], $_GPC['info']);
                if (empty($result)) {
                    message(['code' => '500512', 'msg' => '新地址写入失败'], '', 'ajax');
                }
            } else {
                // 修改收货地址
                $result = $adrClass->setParams($_GPC['id'], $_GPC);
                if (empty($result)) {
                    message(['code' => '500512', 'msg' => '地址修改失败'], '', 'ajax');
                }
            }
            message(['code' => '0000', 'msg' => '成功'], '', 'ajax');
        } else {
            message(['code' => '500403', 'msg' => '错误的请求'], '', 'ajax');
        }
    }

    /**
     * 用户订单.
     */
    public function doMobileOrderList()
    {
        global $_W, $_GPC;
        $adminId = $_W['uniacid'];
        //静态资源目录地址
        $resRoot = $_W['siteroot'].'addons/vrich_luckybox/static';
        //获得微信用户信息
        $userInfo = mc_oauth_userinfo();
        $userId = $userInfo['openid'];
        include_once ROOT_PATH.DIRECTORY_SEPARATOR.'utils'.DIRECTORY_SEPARATOR.'loadUtil.php';
        loadUtilLibrary('Order');
        $orderClass = new Order($userId, $adminId);
        $lastData = $orderClass->lastData();
        // 微信公众号ID
        $webSet = pdo_get('luckybox_web', ['admin_id' => $adminId]);
        //微信分享
        $account_api = WeAccount::create();
        $signPackage = $account_api->getJssdkConfig();
        include $this->template('order');
    }

    /**
     * 商品管理.
     */
    public function doWebGoodsManage()
    {
        global $_W, $_GPC;
        checklogin();
        $adminId = $_W['uniacid'];
        // 默认展示数据列表
        $pageSize = 15;
        $pageIndex = max($_GPC['page'], 1);
        $condition = ['admin_id' => $adminId];
        if ($_W['ispost']) {
            if (!empty($_GPC['sTime']) && !empty(strtotime($_GPC['sTime']))) {
                $condition['up_time >='] = strtotime($_GPC['sTime']);
            }
            if (!empty($_GPC['eTime']) && !empty(strtotime($_GPC['eTime']))) {
                $condition['up_time <'] = strtotime($_GPC['eTime'].' +1 day');
            }
            if (!empty($_GPC['class_id'])) {
                $condition['class_id'] = $_GPC['class_id'];
            }
            if (!empty($_GPC['name'])) {
                $condition['name like'] = '%'.$_GPC['name'].'%';
            }
        }
        $list = $this->_showGoodsList($condition, $pageIndex, $pageSize);
        // 分页导航
        $page = pagination($list['total'], $pageIndex, $pageSize);
        // 类型
        $classNames = $this->_goodsClass($adminId);
        // 根据分页数据，查询其它信息
        $goodsInfoUrl = $this->createWebUrl('goodsInfo');
        $ajaxSellStatus = $this->createWebUrl('setSellStatus');
        include $this->template('goods_manage');
    }

    /**
     * 商品详细页面.
     */
    public function doWebGoodsInfo()
    {
        global $_W, $_GPC;
        checklogin();
        $goodsListUrl = $this->createWebUrl('goodsManage');
        $goodsTypeUrl = $this->createWebUrl('goodsType');
        $ajaxSaveUrl = $this->createWebUrl('saveGoods');
        $adminId = $_W['uniacid'];
        if (!empty($_GPC['gId'])) {
            include_once ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'Goods.php';
            $goodsClass = new Goods($_GPC['gId'], $adminId);
            // 商品详细
            $goodsData = $goodsClass->goodsData();
            if (empty($goodsData)) {
                message('错误的商品信息', $goodsListUrl);
            }
            // 商品详细
            $goodsInfoData = $goodsClass->infoData();
        }
        // 默认输出信息
        // 类型
        $classNames = $this->_goodsClass($adminId);
        // 图片配置
        $options['global'] = false; // 是否显示 global 目录（公共目录）
        $options['extras'] = array(
            'image' => '' // 缩略图img标签的自定义属性及属性值
        , 'text' => 'readonly="readonly"',    // 标签的自定义属性及属性值
        );
        include $this->template('goods_info');
    }

    /**
     * 商品种类.
     */
    public function doWebGoodsType()
    {
        global $_W, $_GPC;
        checklogin();
        $goodsListUrl = $this->createWebUrl('goodsManage');
        if (empty($_GPC['gId'])) {
            message('数据信息错误！', $goodsListUrl);
        }
        $goodsInfoUrl = $this->createWebUrl('goodsInfo');
        $ajaxSaveUrl = $this->createWebUrl('saveGoodsType');
        $adminId = $_W['uniacid'];
        include_once ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'Goods.php';
        include_once ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'GoodsType.php';
        $goodsClass = new Goods($_GPC['gId'], $adminId);
        $goodsData = $goodsClass->goodsData();
        // 商品种类数据
        $goodsTypeClass = new GoodsType($goodsClass);
        $goodsTypeData = $goodsTypeClass->typeData();
        if (empty($goodsTypeData)) {
            // 没有种类数据
            $goodsTypeData = array_fill(0, $goodsData['class_num'], 't');
        } else {
            // 判断数量
            if ($goodsData['class_num'] > count($goodsTypeData)) {
                $t = $goodsData['class_num'] - count($goodsTypeData);
                for ($i = 1; $i <= $t; ++$i) {
                    array_push($goodsTypeData, 't'.$i);
                }
            }
        }
        // 图片配置
        $options['global'] = false; // 是否显示 global 目录（公共目录）
        $options['extras'] = array(
            'image' => '' // 缩略图img标签的自定义属性及属性值
        , 'text' => 'readonly="readonly"',    // 标签的自定义属性及属性值
        );
        include $this->template('goods_type');
    }

    /**
     * 编辑商品信息数据.
     */
    public function doWebSaveGoods()
    {
        global $_W, $_GPC;
        if ($_W['ispost']) {
            $adminId = $_W['uniacid'];
            if (!empty($_GPC['id']) && intval($_GPC['id']) < 1) {
                message(['code' => 400015, 'msg' => '无效的信息数据！'], '', 'ajax');
            }
            include_once ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'Goods.php';
            if (empty($_GPC['id'])) {
                // 新增
                $goodsClass = new Goods(0, $adminId);
                $result = $goodsClass->paramSet($_GPC);
                if (empty($result)) {
                    message(['code' => 500023, 'msg' => $goodsClass->error()], '', 'ajax');
                }
                message(['code' => '0000', 'msg' => '数据创建成功，请接着创建商品种类信息！', 'gId' => $goodsClass->goodsId()], '', 'ajax');
            } else {
                // 修改
                $goodsClass = new Goods($_GPC['id'], $adminId);
                include_once ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'GoodsType.php';
                $goodsTypeClass = new GoodsType($goodsClass);
                $goodsClass->setTypeClass($goodsTypeClass);
                $result = $goodsClass->paramSet($_GPC, 'modify');
                if (empty($result)) {
                    message(['code' => 500024, 'msg' => $goodsClass->error()], '', 'ajax');
                }
                message(['code' => '0000', 'msg' => '数据修改成功！'], '', 'ajax');
            }
        } else {
            message(['code' => 400012, 'msg' => '无效的数据访问！'], '', 'ajax');
        }
    }

    /**
     * 编辑商品种类信息.
     */
    public function doWebSaveGoodsType()
    {
        global $_W, $_GPC;
        if ($_W['ispost']) {
            if (!empty($_GPC['id']) && intval($_GPC['id']) < 1) {
                message(['code' => 400015, 'msg' => '无效的信息数据！'], '', 'ajax');
            }
            if (!isset($_GPC['num']) || !isset($_GPC['t_img']) || !isset($_GPC['probability'])) {
                message(['code' => 400017, 'msg' => '提交信息数据不匹配！'], '', 'ajax');
            }
            $adminId = $_W['uniacid'];
            include_once ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'Goods.php';
            $goodsClass = new Goods($_GPC['id'], $adminId);
            if (empty($goodsClass->goodsId())) {
                message(['code' => 500017, 'msg' => '错误的商品数据信息！'], '', 'ajax');
            }
            include_once ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'GoodsType.php';
            $gTypeClass = new GoodsType($goodsClass);
            $result = $gTypeClass->processParams($_GPC);
            if (empty($result)) {
                message(['code' => 500019, 'msg' => $gTypeClass->error()], '', 'ajax');
            } else {
                message(['code' => '0000', 'msg' => '种类数据保存成功'], '', 'ajax');
            }
        } else {
            message(['code' => 400012, 'msg' => '无效的数据访问！'], '', 'ajax');
        }
    }

    /**
     * 设置商品上下架状态
     */
    public function doWebSetSellStatus()
    {
        global $_W, $_GPC;
        if ($_W['ispost']) {
            $adminId = $_W['uniacid'];
            if (empty($_GPC['gId']) || intval($_GPC['gId']) < 1) {
                message(['code' => 500103, 'msg' => '无效的数据！'], '', 'ajax');
            }
            include_once ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'Goods.php';
            $goodsClass = new Goods($_GPC['gId'], $adminId);
            if (empty($goodsClass->goodsId())) {
                message(['code' => 500105, 'msg' => '无效的商品数据！'], '', 'ajax');
            }
            $result = $goodsClass->setSellStatus($_GPC['type']);
            if ($result) {
                message(
                    ['code' => '0000', 'msg' => '设置成功！', 'time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])], '', 'ajax'
                );
            } else {
                message(['code' => 500203, 'msg' => $goodsClass->error()], '', 'ajax');
            }
        } else {
            message(['code' => 400012, 'msg' => '无效的数据访问！'], '', 'ajax');
        }
    }

    /**
     * 商品列表信息.
     *
     * @param array|string $condition   查询条件
     * @param int          $currentPage 当前页码
     * @param int          $pageSize    每页展示数量
     *
     * @return array [data:记录数据, total:记录总数]
     */
    private function _showGoodsList($condition, $currentPage, $pageSize = 15)
    {
        // 分页数据
        $data = pdo_getslice('luckybox_goods', $condition, [$currentPage,  $pageSize], $total, '', '', ['sort_num desc', 'id asc']);

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 商品类型名称.
     *
     * @return array|bool|mixed 返回以 类型ID 为 key 的数据记录
     */
    private function _goodsClass($adminId)
    {
        return pdo_fetchall(
            'select id, name from '.tablename('luckybox_goods_class').' where admin_id in (:adminId)', [':adminId' => $adminId], 'id'
        );
    }

    /**
     * 商品类型数量.
     *
     * @param array $goodsIds
     *
     * @return array|bool|mixed 返回以 商品ID 为 key 的数据记录
     */
    private function _goodsTypeNum($goodsIds)
    {
        if (empty($goodsIds) || !is_array($goodsIds)) {
            return [];
        }

        return pdo_fetchall(
            'select goods_id, count(*) as num from '.tablename('luckybox_goods_type')
            .' where goods_id in (:idList) group by goods_id', [':idList' => implode(',', $goodsIds)], 'goods_id'
        );
    }

    /**
     * 订单列表.
     */
    public function doWebOrderManage()
    {
        global $_W, $_GPC;
        checklogin();
        require_once ROOT_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'express.inc.php';
        // 微信公众号ID
        $adminUserId = $_W['uniacid'];
        // 总销售额，本周销售额，本月销售额
        $sumPrice = pdo_fetchcolumn(
            'select sum(`price`) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `status` >= 1 and `is_del` = 0', [':admin_id' => $adminUserId]
        );
        $date = $this->_dateArea('week');
        $weekPrice = pdo_fetchcolumn(
            'select sum(`price`) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `create_time` >= :st and `create_time` < :et  and `status` >= 1 and `is_del` = 0', [':admin_id' => $adminUserId, ':st' => $date[0], ':et' => $date[1]]
        );
        $date = $this->_dateArea('month');
        $monthPrice = pdo_fetchcolumn(
            'select sum(`price`) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `create_time` >= :st and `create_time` < :et  and `status` >= 1 and `is_del` = 0', [':admin_id' => $adminUserId, ':st' => $date[0], ':et' => $date[1]]
        );
        // 今日订单，今日成交额，今日已发货
        $date = $this->_dateArea('today');
        $todayNum = pdo_fetchcolumn(
            'select count(*) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `create_time` >= :st and `create_time` < :et  and `status` >= 1 and `is_del` = 0', [':admin_id' => $adminUserId, ':st' => $date[0], ':et' => $date[1]]
        );
        $todayPrice = pdo_fetchcolumn(
            'select sum(`price`) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `create_time` >= :st and `create_time` < :et  and `status` >= 1 and `is_del` = 0', [':admin_id' => $adminUserId, ':st' => $date[0], ':et' => $date[1]]
        );
        $todaySendNum = pdo_fetchcolumn(
            'select count(*) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `create_time` >= :st and `create_time` < :et and `status` = 2', [':admin_id' => $adminUserId, ':st' => $date[0], ':et' => $date[1]]
        );
        // 昨日订单，昨日成交额，昨日已发货
        $date = $this->_dateArea('yesterday');
        $yesterdayNum = pdo_fetchcolumn(
            'select count(*) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `create_time` >= :st and `create_time` < :et  and `status` >= 1 and `is_del` = 0', [':admin_id' => $adminUserId, ':st' => $date[0], ':et' => $date[1]]
        );
        $yesterdayPrice = pdo_fetchcolumn(
            'select sum(`price`) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `create_time` >= :st and `create_time` < :et  and `status` >= 1 and `is_del` = 0', [':admin_id' => $adminUserId, ':st' => $date[0], ':et' => $date[1]]
        );
        $yesterdaySendNum = pdo_fetchcolumn(
            'select count(*) from '.tablename('luckybox_order').' where `admin_id` = :admin_id and `create_time` >= :st and `create_time` < :et and `status` = 2 and `is_del` = 0', [':admin_id' => $adminUserId, ':st' => $date[0], ':et' => $date[1]]
        );
        // 今天的订单列表
        $pageIndex = max($_GPC['page'], 1);
        $pageSize = 15;
        // -------- 分页数据 --------
        if (!empty($_GPC['sTime']) && !empty($_GPC['eTime'])
            && !empty(strtotime($_GPC['sTime'])) && !empty(strtotime($_GPC['eTime']))
        ) {
            $timeCondition = " o.create_time >= '".date('Y-m-d', strtotime($_GPC['sTime']))."' and ";
            $timeCondition .= " o.create_time <= '".date('Y-m-d 23:59:59', strtotime($_GPC['eTime']))."' and ";
            // 订单总金额
            $totalSumPrice = pdo_fetchcolumn('select sum(`price`) from '.tablename('luckybox_order')." o where {$timeCondition} `admin_id` = :admin_id  and o.`status` >= 1 and o.`is_del` = 0", [':admin_id' => $adminUserId]);
        } else {
            $timeCondition = '';
            $totalSumPrice = 0;
        }
        // 总数量
        $total = pdo_fetchcolumn('select count(*) from '.tablename('luckybox_order')." o where {$timeCondition} `admin_id` = :admin_id  and `status` >= 1 and `is_del` = 0", [':admin_id' => $adminUserId]);
        // 分页数据
        $data = pdo_fetchall(
            'select o.*, g.name as goods_name, e.express_id, e.express_name_remark, e.express_order from '.tablename('luckybox_order').' o left join 
            '.tablename('luckybox_goods').' g on (o.goods_id = g.id) left join 
             '.tablename('luckybox_order_express')." e on (o.id = e.order_id) where  {$timeCondition} o.admin_id = ".$adminUserId.' and o.`status` >= 1 and o.`is_del` = 0 order by `create_time` desc limit '.($pageIndex - 1) * $pageSize.','.$pageSize
        );
        // 分页导航 html
        $page = pagination($total, $pageIndex, $pageSize);
        $doReadControl = $this->createWebUrl('setOrderRead');
        $exportControl = $this->createWebUrl('exportData');
        // 保存订单快递信息
        $ajaxSingleExpress = $this->createWebUrl('SingleExpressSave');
        include $this->template('order_manage');
    }

    /**
     * 异步设置阅读状态
     */
    public function doWebSetOrderRead()
    {
        global $_GPC, $_W;
        $adminId = $_W['uniacid'];
        if (empty($adminId)) {
            message(['code' => '400101', 'msg' => '未启用任何公众号！'], '', 'ajax');
        }
        if (empty($_GPC['ids']) || !is_array($_GPC['ids'])) {
            message(['code' => '400105', 'msg' => '没有操作的数据！'], '', 'ajax');
        }
        // 查询数量
        $selectCount = pdo_fetchcolumn('select count(*) from '.tablename('luckybox_order').' where `id` in ('.implode(',', $_GPC['ids']).') and `admin_id` = '.$adminId.' and `status` >= 1 and `is_del` = 0');
        if ($selectCount != count($_GPC['ids'])) {
            message(['code' => '400106', 'msg' => '数据信息数量不符合！'], '', 'ajax');
        }
        // 更新数据
        $result = pdo_query('update '.tablename('luckybox_order').' set is_read = 1 where `id` in ('.implode(',', $_GPC['ids']).') and `admin_id` = '.$adminId);
        if ($result) {
            message(['code' => '0000', 'msg' => '成功！'], '', 'ajax');
        } else {
            message(['code' => '400107', 'msg' => '数据保存失败！'], '', 'ajax');
        }
    }

    /**
     * 数据导出.
     */
    public function doWebExportData()
    {
        global $_GPC, $_W;
        checklogin();
        if (empty($_GPC['sTime']) || empty($_GPC['eTime'])
            || empty(strtotime($_GPC['sTime'])) || empty(strtotime($_GPC['eTime']))
        ) {
            message('未有可导出的记录！', 'referer');
        }
        $adminId = $_W['uniacid'];
        if (empty($adminId)) {
            message('未启用任何公众号！', 'referer');
        }
        $timeCondition = " o.create_time >= '".date('Y-m-d', strtotime($_GPC['sTime']))."' and ";
        $timeCondition .= " o.create_time <= '".date('Y-m-d 23:59:59', strtotime($_GPC['eTime']))."' and ";
        // 总数量
        $total = pdo_fetchcolumn('select count(*) from '.tablename('luckybox_order')." o where {$timeCondition} `admin_id` = :admin_id and `status` >= 1 and `is_del` = 0", [':admin_id' => $adminId]);
        if ($total < 1) {
            message('未有可导出的数据！', 'referer');
        }
        $pageSize = 2000;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition:filename=data_'.date('Y-m-d', strtotime($_GPC['sTime'])).'-'.date('Y-m-d ',
                strtotime($_GPC['eTime'])).'.csv');
        $field = ['订单号', '产品名称', '联系人', '联系电话', '下单时间', '订单总金额', '数量', '订单状态', '状态', '快递名称', '快递单号', '快递时间'];
        echo iconv('utf-8', 'gbk', implode(',', $field))."\n";
        if ($total > $pageSize) {
            $data = pdo_fetchall(
                'select o.*, g.name as goods_name, e.express_id, e.express_name_remark, e.express_order, e.create_time as express_time from '.tablename('luckybox_order').' o left join 
            '.tablename('luckybox_goods').' g on (o.goods_id = g.id) left join 
             '.tablename('luckybox_order_express')." e on (o.id = e.order_id) where  {$timeCondition} o.admin_id = ".$adminId.' and o.`status` >= 1 and o.`is_del` = 0 order by `create_time` desc'
            );
            $this->_writeCsv($data);
        } else {
            for ($pageIndex = 1; $pageIndex <= ceil($total / $pageSize); ++$pageIndex) {
                $data = pdo_fetchall(
                    'select o.*, g.name as goods_name, e.express_id, e.express_name_remark, e.express_order, e.create_time as express_time from '.tablename('luckybox_order').' o left join 
            '.tablename('luckybox_goods').' g on (o.goods_id = g.id) left join 
             '.tablename('luckybox_order_express')." e on (o.id = e.order_id) where  {$timeCondition} o.admin_id = ".$adminId
                    .' and o.`status` >= 1 and o.`is_del` = 0 order by `create_time` desc limit '.($pageIndex - 1) * $pageSize.','.$pageSize
                );
                $this->_writeCsv($data);
            }
        }

        return;
    }

    /**
     * 数据写入 csv.
     *
     * @param $multiArr
     */
    private function _writeCsv($multiArr)
    {
        if (empty($multiArr) || !is_array($multiArr)) {
            return;
        }
        require_once ROOT_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'express.inc.php';
        foreach ($multiArr as $arr) {
            if ($arr['status'] == 1) {
                $statusStr = '未发货';
            } elseif ($arr['status'] == 2) {
                $statusStr = '已发货';
            } else {
                $statusStr = '未支付';
            }
            $data = [
                $arr['id'], $arr['goods_name'], $arr['adr_name'], $arr['adr_phone'], $arr['create_time'], $arr['price'] / 100, $arr['num'], $statusStr, $arr['is_read'] ? '已读' : '未读', $arr['express_id'] ? $express_list[$arr['express_id']] : $arr['express_name_remark'], $arr['express_order'], $arr['express_time'],
            ];
            echo iconv('utf-8', 'gbk', implode(',', $data))."\n";
        }
    }

    /**
     * 订单详情页面.
     */
    public function doWebOrderInfo()
    {
        global $_GPC, $_W;
        checklogin();
        // 判断是否有订单ID信息
        if (empty($_GPC['orderId'])) {
            message('授权错误！', 'referer');
        }
        require_once ROOT_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'express.inc.php';
        // 订单列表地址
        $orderManagerControl = $this->createWebUrl('orderManage');
        // 微信公众号ID
        $adminId = $_W['uniacid'];
        // 查询订单信息
        $data = pdo_get('luckybox_order', ['admin_id' => $adminId, 'id' => $_GPC['orderId']]);
        if (empty($data)) {
            message('数据不存在，请核查信息！', 'referer');
        }
        // 商品信息
        $goodsInfo = pdo_get('luckybox_goods', ['id' => $data['goods_id']]);
        // 查询快递信息
        $express = pdo_get('luckybox_order_express', ['order_id' => $data['id']]);
        // 保存订单快递信息
        $ajaxSingleExpress = $this->createWebUrl('SingleExpressSave');
        include $this->template('order_info');
    }

    /**
     * 保存快递信息.
     */
    public function doWebSingleExpressSave()
    {
        global $_GPC, $_W;
        $adminId = $_W['uniacid'];
        if ($_W['ispost'] && !empty($adminId)) {
            // 是否 POST 请求
            if (empty($_GPC['id']) || intval($_GPC['id']) < 1) {
                message(['code' => '400104', 'msg' => '错误的数据编号信息！'], '', 'ajax');
            }
            // 更新发货状态，快递名称，快递单号
            $orderResult = pdo_update('luckybox_order', ['status' => $_GPC['status']], ['id' => $_GPC['id'], 'admin_id' => $adminId]
            );
            $expressResult = pdo_insert('luckybox_order_express', ['order_id' => $_GPC['id'], 'express_id' => $_GPC['express_id'], 'express_name_remark' => $_GPC['express_name'], 'express_order' => $_GPC['express_order'], 'create_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
                ], true
            );
            if ($orderResult || $expressResult) {
                message(['code' => '0000', 'msg' => '更新成功'], '', 'ajax');
            } else {
                message(['code' => '400105', 'msg' => '更新失败！'], '', 'ajax');
            }
        } else {
            message(['code' => '400102', 'msg' => '错误的请求！'], '', 'ajax');
        }
    }

    /**
     * 网站基础设置.
     */
    public function doWebSetting()
    {
        global $_W, $_GPC;
        checklogin();
        // 微信公众号ID
        $adminUserId = $_W['uniacid'];
        $webSet = pdo_get('luckybox_web', ['admin_id' => $adminUserId]);
        $options['global'] = false; // 是否显示 global 目录（公共目录）
        $options['extras'] = array(
            'image' => '' // 缩略图img标签的自定义属性及属性值
            , 'text' => 'readonly="readonly"',    // 标签的自定义属性及属性值
        );
        $currUrl = $this->createWebUrl('setting');
        $postUrl = '/web'.substr($currUrl, 1);
        $classUrl = $this->createWebUrl('class');
        if ($_W['ispost']) {
            if (empty($adminUserId)) {
                message('未启用任何公众号！', $currUrl, 'error');
            }
            if (empty($_GPC['name'])) {
                message('请填写网站名称！', $currUrl, 'error');
            }
            $arr = [
                'admin_id' => $adminUserId, 'name' => $_GPC['name'], 'share_title' => $_GPC['share_title'], 'share_subhead' => $_GPC['share_subhead'], 'share_icon' => $_GPC['share_icon'],
            ];
            if (empty($webSet)) {
                $arr['create_time'] = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);
            }
            $result = pdo_insert('luckybox_web', $arr, true);
            if (empty($result)) {
                message('基础信息保存失败！', $currUrl, 'error');
            } else {
                message('基础信息保存成功！', $currUrl, 'success');
            }
        }
        include $this->template('web_set');
    }

    /**
     * 商品分类管理页面.
     */
    public function doWebClass()
    {
        global $_W, $_GPC;
        checklogin();
        $setUrl = $this->createWebUrl('setting');
        $ajaxUrl = $this->createWebUrl('classSave');
        $adminId = $_W['uniacid'];
        $pageIndex = max($_GPC['page'], 1);
        $pageSize = 15;
        // 分页数据
        $data = pdo_getslice('luckybox_goods_class', ['admin_id' => $adminId], [$pageIndex,  $pageSize], $total);
        // 分页导航 html
        $page = pagination($total, $pageIndex, $pageSize);
        include $this->template('class_manage');
    }

    /**
     * 商品分类数据处理.
     */
    public function doWebClassSave()
    {
        global $_W, $_GPC;
        $adminId = $_W['uniacid'];
        if (empty($adminId)) {
            message(['code' => '400101', 'msg' => '未启用任何公众号！'], '', 'ajax');
        }
        if (empty($_GPC['name']) && empty($_GPC['status'])) {
            message(['code' => '400102', 'msg' => '数据提交错误！'], '', 'ajax');
        }
        if (isset($_GPC['name'])) {
            $arr['name'] = $_GPC['name'];
        }
        if (empty($_GPC['id'])) {
            // 新增商品分类
            $arr['create_time'] = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);
            $arr['admin_id'] = $adminId;
            $result = pdo_insert('luckybox_goods_class', $arr);
            $insertId = pdo_insertid();
        } else {
            // 修改更新
            $data = pdo_get('luckybox_goods_class', ['id' => $_GPC['id']]);
            if (empty($data)) {
                message(['code' => '400104', 'msg' => '数据不存在，无法更新！'], '', 'ajax');
            }
            if (isset($_GPC['status'])) {
                $arr['status'] = $data['status'] ? 0 : 1;
            }
            $result = pdo_update('luckybox_goods_class', $arr, ['id' => $_GPC['id']]);
            $insertId = 0;
        }
        if (empty($result)) {
            message(['code' => '400103', 'msg' => '保存失败！'], '', 'ajax');
        } else {
            message(['code' => '0000', 'msg' => '成功！', 'id' => $insertId, 'time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])], '', 'ajax');
        }
    }

    /**
     * 时间范围.
     *
     * @param string $type 时间类型;today, yesterday, week, month
     *
     * @return array 返回时间范围[起始时间，结束时间)
     */
    private function _dateArea($type)
    {
        switch ($type) {
            case 'today':
                return [
                    date('Y-m-d', $_SERVER['REQUEST_TIME']), date('Y-m-d', strtotime('+1 day')),
                ];
            case 'yesterday':
                return [
                    date('Y-m-d', strtotime('-1 day')), date('Y-m-d', $_SERVER['REQUEST_TIME']),
                ];
            case 'week':
                $currDate = empty($date) ? $_SERVER['REQUEST_TIME'] : strtotime($date);
                $w = date('w', $currDate);
                if ($w == 0) {
                    $start = date('Y-m-d', $currDate - 6 * 86400);
                    $end = date('Y-m-d', $currDate);
                } else {
                    $start = date('Y-m-d', $currDate - ($w - 1) * 86400);
                    $end = date('Y-m-d', $currDate + (7 - $w) * 86400);
                }

                return [$start, $end];
            case 'month':
                return [
                    date('Y-m-01', $_SERVER['REQUEST_TIME']), date('Y-m-01', strtotime('+1 month')),
                ];
        }

        return [];
    }

    /**
     * 获取20位订单数据.
     *
     * @return bool|string
     */
    private function _getNumOrder()
    {
        $date = date('YmdHis', $_SERVER['REQUEST_TIME']);
        $autoId = mt_rand(100000, 999999);

        return $date.$autoId;
    }
}
