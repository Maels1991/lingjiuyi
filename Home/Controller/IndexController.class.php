<?php
namespace Home\Controller;
use Think\Controller;
class IndexController extends CommonController {

    //主页
    public function index(){

        //首页右上角分类
        if(!session('?cates')){
            $cates = D('Category') -> field('id,pid,cate_name') -> select();
            $_SESSION['cates'] = $cates;
        }

        //四个分类对应的20个商品展示
        $gmodel   = D('Goods');
        $fields   = 'goods_id i,goods_name n,goods_bigprice bp,goods_price p,goods_big_img bi,is_act';
        $addA     = ',b.id cid,b.cate_name cn';
        $g = $fields.$addA;
        $three_goods    = $gmodel -> query("SELECT $g FROM zhouyuting_goods AS a LEFT JOIN zhouyuting_category AS b ON a.cate_id=b.id
WHERE 20 > ( SELECT COUNT(*) FROM zhouyuting_goods WHERE cate_id=a.cate_id AND goods_id > a.goods_id ) AND cate_id in (246,311,213,186)
ORDER BY a.click_num DESC");
        $cate_goods       = change_array($three_goods,'cid');

        //六个分类介绍
        $six_cates = D('Category') -> alias('a')
            -> field('a.id,cate_name cn,cate_img img,count(cate_id) sum')
            -> join('join zhouyuting_goods b  on a.id = b.cate_id')
            -> limit(0,6) -> group('cate_id') -> select();
        //后期添加条件 where(pid=2)

        //六个店铺对应的16个商品展示
        $joinB      = 'left join zhouyuting_shop b on zhouyuting_goods.shop_id = b.id';
        $addB       = ',b.id spid,b.shop_name';
        $shop_goods = $gmodel -> field($fields.$addB) -> join($joinB) -> order('click_num desc') -> select();
        //后期添加条件where(is_act=1),并且每个店铺规定活动商品数量
        $new_shop_goods = change_array($shop_goods,'spid');

        //今日限时活动商品,4个商品
        $act_goods = $gmodel -> field($fields) -> limit(0,4) -> order('click_num desc') -> select();//后期设置一个今日活动的专题

        //三个大类商品，一个分类下6个商品
        $f = $fields.$addA;
        $three_goods    = $gmodel -> query("SELECT $f FROM zhouyuting_goods AS a LEFT JOIN zhouyuting_category AS b ON a.cate_id=b.id
WHERE 6 > ( SELECT COUNT(*) FROM zhouyuting_goods WHERE cate_id=a.cate_id AND goods_id > a.goods_id ) AND cate_id in (167,169,186)
ORDER BY a.click_num DESC");
        $new_three_goods = change_array($three_goods,'cid');

        $this -> assign('cate_goods',$cate_goods);
        $this -> assign('six_cates',$six_cates);
        $this -> assign('new_shop_goods',$new_shop_goods);
        $this -> assign('act_goods',$act_goods);
        $this -> assign('new_three_goods',$new_three_goods);
        $this -> display();
    }

    public function cart(){
        $this -> check_login();
        $uid = session('userinfo.id');
        $fields = 'a.id ci, a.goods_id gi,a.number num,b.goods_name gn,b.goods_price p,goods_bigprice bp,goods_small_img simg';
        $cartlist = D('Cart') -> alias('a') -> field($fields) -> where("user_id = $uid") -> join('zhouyuting_goods b on a.goods_id = b.goods_id') -> select();
        $cartlist ? $this -> ajaxReturnData(10000,'success',$cartlist) : $this -> ajaxReturnData(0,'购物车为空');
    }

    //搜索页面
    public function search(){
        if(IS_AJAX){
            $key      = I('post.keyword') ? I('post.keyword') : '';//搜索关键字
            $p        = I('post.p') ? I('post.p','','intval') : 1;//页码
            $pagesize = 20;//每页显示商品数量
            $start    = ($p - 1) * $pagesize;//开始行数
            $min      = I('post.min','','intval');
            $max      = I('post.max','','intval');
            $where = array(
                'goods_name' => ['LIKE',"%$key%"],
                'goods_price' => ['BETWEEN',$min.','.$max],
            );//搜索条件

            $order = I('post.sorts');
            $price = I('post.prices');
            if((!empty($order) || $order == '0') && empty($price)){
                $order = ($order == 'a') ? 'sale_num desc' : ($order == 'b' ? 'click_num desc' : ($order == 'c' ? 'comment_num desc' : 'goods_id asc'));
            }else{
                $order = $price;
                $order = $order == 'ltoh' ? 'goods_price asc' : 'goods_price desc' ;
            }


            $fields = 'goods_id i,goods_name n,goods_bigprice bp,goods_price p,goods_big_img bi,is_act';
            $count  = D('Goods') -> where($where) -> count();
            $list = D('Goods') -> field($fields) -> where($where) -> limit($start,$pagesize) -> order($order) -> select();
            empty($list) && ($p === 1) ? $this -> ajaxReturnData(0,'到底了！',compact('count','p','pagesize')) : true;
            empty($list) ? $this->ajaxReturnData(10001,'没有搜索到您想要的商品！',compact('list','count','p','pagesize')) : $this->ajaxReturnData(10000,'success',compact('list','count','p','pagesize','start'));

        }else{
            $category = D('Category') -> field('id ci,cate_name cn') -> where('pid = 0') -> select();
            $this -> assign('cate',$category);
            $this -> display();
        }
    }

    public function aboutUs(){
        $this -> display();
    }

    public function shopSingle(){
        $this -> display();
    }

    public function contact(){
        $this -> display();
    }

    //商品详情页弹窗
    public function goodsDetail(){
        $id      = I('get.id','','intval');
        $goods   = D('Goods') -> where(" goods_id = $id ") -> find();
        $goods['click_num'] += 1;
        D('Goods') -> save($goods);
        //获取商品相册
        $pic_img = D('Goodspics') -> field('pics_big pb,pics_sma ps') -> where("goods_id = $id") -> select();
        //获取商品属性
        $attrs   = D('goods_attr') -> alias('a') -> field('a.id gaid,a.attr_id ai,a.attr_value value,b.attr_name name,b.attr_type type') -> where("goods_id = $id") -> join('zhouyuting_attribute b on a.attr_id = b.attr_id') -> select();
        $new_attrs = change_array($attrs,'ai');

        $this -> ajaxReturn(compact('pic_img','new_attrs'));

    }


}