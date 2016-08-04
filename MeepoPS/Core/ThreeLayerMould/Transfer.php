<?php
/**
 * 传输层
 * 只接受用户的链接, 不做任何的业务逻辑。
 * 接收用户发送的数据转发给Business层, 再将Business层返回的结果发送给用户
 * Created by lixuan868686@163.com
 * User: lane
 * Date: 16/6/29
 * Time: 下午3:20
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core\ThreeLayerMould;

class Transfer {
    //confluence的IP
    public $confluenceIp;
    //confluence的端口
    public $confluencePort;

    //和Business通讯的内部IP(Business链接到这个IP)
    public $innerIp = '0.0.0.0';
    //和Business通讯的内部端口(Business链接到这个端口)
    public $innerPort = 19912;

    //客户端列表
    public static $clientList = array();
    //本类只操作API,不操作$this。因为本类并没有继承MeepoPS
    private $_apiClass;
    //TransferAndBusinessService对象
    private $_transferAndBusinessService;
    //TransferAndConfluenceService对象
    private $_transferAndConfluenceService;

    public function __construct($apiName, $host, $port, array $contextOptionList=array())
    {
        $this->_transferAndBusinessService = new TransferAndBusinessService();
        $this->_transferAndConfluenceService = new TransferAndConfluenceService();
        $this->_apiClass = new $apiName($host, $port, $contextOptionList);
        $this->_apiClass->callbackStartInstance = array($this, 'callbackTransferStartInstance');
        $this->_apiClass->callbackConnect = array($this, 'callbackTransferConnect');
        $this->_apiClass->callbackNewData = array($this, 'callbackTransferNewData');
        $this->_apiClass->callbackConnectClose = array($this, 'callbackTransferConnectClose');
    }

    public function setApiClassProperty($name, $value){
        $this->_apiClass->$name = $value;
    }

    /**
     * 进程启动时, 监听端口, 提供给Business, 同时, 链接到Confluence
     */
    public function callbackTransferStartInstance($instance){
        $this->innerPort = $this->innerPort + $instance->id;
        //监听一个端口, 用来做内部通讯(Business会链接这个端口)。
        $this->_transferAndBusinessService->transferIp = $this->innerIp;
        $this->_transferAndBusinessService->transferPort = $this->innerPort;
        $this->_transferAndBusinessService->listenBusiness();
        //向中心机(Confluence层)发送自己的地址和端口, 以便Business感知。
        $this->_transferAndConfluenceService->transferIp = $this->innerIp;
        $this->_transferAndConfluenceService->transferPort = $this->innerPort;
        $this->_transferAndConfluenceService->confluenceIp = $this->confluenceIp;
        $this->_transferAndConfluenceService->confluencePort = $this->confluencePort;
        $this->_transferAndConfluenceService->connectConfluence();
    }

    /**
     * 回调函数 - 客户端的新链接
     * @param $connect
     */
    public function callbackTransferConnect($connect){
        $connect->unique_id = Tool::encodeClientId($this->innerIp, $this->innerPort, $connect->id);
        self::$clientList[$connect->id] = $connect;
        $connect->send('UniqueId=' . $connect->unique_id);
    }

    /**
     * 回调函数 - 客户端发来的新消息
     * @param $connect
     * @param $data
     */
    public function callbackTransferNewData($connect, $data){
        //把消息转发给Business层处理
        $this->_transferAndBusinessService->sendToBusiness($connect, $data);
    }

    /**
     * 回调函数 - 客户端断开链接
     * @param $connect
     */
    public function callbackTransferConnectClose($connect){

    }
}