<?php

namespace App\Services;
// use Illuminate\Support\Facades\DB;

class WebrtcService
{
    private $redis;
    public function __construct()
    {
        var_dump("WebrtcService");
        $this->redis=new \Redis();
        $this->redis->connect(env('REDIS_HOST'), env('REDIS_PORT'));
    }
    public function __call($name, $arguments){
        echo '方法不存在调用我';
        echo '<br/>';
        echo '方法名为:'. $name;
    }
    private function pushMessage($server,$fd,$MessageData){
        $MessageData=json_encode($MessageData);
        $server->push($fd, $MessageData);
    }
    private function pushRoomList($server, $frame){

    }
    public function getFd($server, $frame){
        var_dump('getFd');
        $serverMessageData=['type'=>'getFdCallback','data'=>['serverFd'=>$frame->fd]];
        $this->pushMessage($server,$frame->fd,$serverMessageData);
    }

    public function getRoomList($server, $frame){
        var_dump('getRoomList');

        $getRoomList=$this->redis->zRange('watchtogether:privateroomlist', 0, -1, true);

        $getRoomMessageData=['type'=>'getRoomListCallback','data'=>$getRoomList];
        $getRoomMessageData=json_encode($getRoomMessageData);
        $server->push($frame->fd, $getRoomMessageData);
        var_dump($getRoomList);
        //添加访客集合
         $this->redis->zAdd('watchtogether:visitorlist', $frame->fd, $frame->fd);

    }
    public function leaveRoom($server,$fd){
        var_dump('leaveRoom');

        $user=$this->redis->hGetAll('watchtogether:user:'.$fd);
        var_dump(__LINE__);
        var_dump($user);
        var_dump(__LINE__);
        if (empty($user)) {
            $this->redis->zRem('watchtogether:visitorlist', $fd);
        }else{
            if ($user['type']=='creater') {
                $this->createrLeaveRoom($server,$fd);
            }elseif ($user['type']=='joiner') {
                $this->joinerLeaveRoom($server,$fd);
            }

        }

        // $hExists=$this->redis->hExists('watchtogether_roomlist',$fd);
        // if($hExists){
        //     $this->redis->hDel('watchtogether_roomlist',$fd);
        // }
    }
    private function createrLeaveRoom($server,$fd){
        //删除redis中的user
        $this->redis->del('watchtogether:user:'.$fd);

        //取出room的joiner进行通知
        $room=$this->redis->hGetAll('watchtogether:room:'.$fd);
        //给joiner发送退出房间的消息
        if (!empty($room['joiner'])) {
            $clientMessageData=['type'=>'serverBye','data'=>'房主关闭房间!'];
            $clientMessageData=json_encode($clientMessageData);
            $server->push($room['joiner'], $clientMessageData);
            //删除加入者
            $this->redis->del('watchtogether:user:'.$room['joiner']);
        }
        //删除redis中的room
        $this->redis->del('watchtogether:room:'.$fd);
        //删除redis中的privateroomlist
        $this->redis->zRem('watchtogether:privateroomlist', $fd);
    }
    private function joinerLeaveRoom($server,$fd){
        $user=$this->redis->hGetAll('watchtogether:user:'.$fd);
        //删除redis中的user
        $this->redis->del('watchtogether:user:'.$fd);

        $number=$this->redis->zScore('watchtogether:privateroomlist', $user['roomId']);
        var_dump($number);
        if ($number == 2) {
            //将room房间joiner置空
            $this->redis->hSet('watchtogether:room:'.$user['roomId'], 'joiner', '');
            //修改房间列表的人数
            $this->redis->zAdd('watchtogether:privateroomlist', 1, $user['roomId']);
            //给房主发送退出房间的消息
            $serverMessageData=['type'=>'clientBye','data'=>'观看者退出房间!'];
            $serverMessageData=json_encode($serverMessageData);
            $server->push($user['roomId'], $serverMessageData);

        }
    }
    public function joinerOutRoom($server,$frame){
        $fd = $frame->fd;
        $user=$this->redis->hGetAll('watchtogether:user:'.$fd);
        //删除redis中的user
        $this->redis->del('watchtogether:user:'.$fd);

        $number=$this->redis->zScore('watchtogether:privateroomlist', $user['roomId']);
        var_dump($number);
        if ($number == 2) {
            //将room房间joiner置空
            $this->redis->hSet('watchtogether:room:'.$user['roomId'], 'joiner', '');
            //修改房间列表的人数
            $this->redis->zAdd('watchtogether:privateroomlist', 1, $user['roomId']);
            //给房主发送退出房间的消息
            $serverMessageData=['type'=>'clientBye','data'=>'观看者退出房间!'];
            $serverMessageData=json_encode($serverMessageData);
            $server->push($user['roomId'], $serverMessageData);

        }
    }
    public function createRoom($server, $frame){
        var_dump('createRoom');
        $messageData=json_decode($frame->data,true);
        var_dump($messageData);

        //user存入redis
        $now=date("Y-m-d H:i:s");
        $this->redis->hSet('watchtogether:user:'.$frame->fd, 'fd', $frame->fd);
        $this->redis->hSet('watchtogether:user:'.$frame->fd, 'roomId', $frame->fd);
        $this->redis->hSet('watchtogether:user:'.$frame->fd, 'type', 'creater');
        $this->redis->hSet('watchtogether:user:'.$frame->fd, 'createTime', $now);
        //user存入mysql
        //-----代码省略--
        //room存入redis
        $this->redis->hSet('watchtogether:room:'.$frame->fd, 'pwd', $messageData['data']['roomPwd']);
        $this->redis->hSet('watchtogether:room:'.$frame->fd, 'id', $frame->fd);
        $this->redis->hSet('watchtogether:room:'.$frame->fd, 'creater', $frame->fd);
        $this->redis->hSet('watchtogether:room:'.$frame->fd, 'joiner', '');
        $this->redis->hSet('watchtogether:room:'.$frame->fd, 'description', '');
        $this->redis->hSet('watchtogether:room:'.$frame->fd, 'createTime', $now);
        //room存入mysql
        //-----代码省略--
        //privateroomlist房间列表存入redis
        $this->redis->zAdd('watchtogether:privateroomlist', 1, $frame->fd);

        $serverMessageData=['type'=>'createRoomCallback'];
        $this->pushMessage($server,$frame->fd,$serverMessageData);

    }
    public function joinRoom($server, $frame){
        var_dump("joinRoom");
        $messageData=json_decode($frame->data,true);
        $serverFd=$messageData['data']['roomId'];
        $roomId=$serverFd=(int)$serverFd;

        $count=$this->redis->zScore('watchtogether:privateroomlist', $serverFd);
        var_dump($count);
        if ($count) {
            //判断房间是否满了
            if ($count == 1) {
                //查询密码
                $pwd=$this->redis->hGet('watchtogether:room:'.$roomId, 'pwd');
                if ($pwd == $messageData['data']['roomPwd']) {
                    if($server->isEstablished($serverFd)){
                        $serverMessageData=['type'=>'clientJoinRoom','data'=>['clientFd'=>$frame->fd,'serverFd'=>$serverFd]];
                        $this->pushMessage($server,$serverFd,$serverMessageData);

                        $clientMessageData=['type'=>'joinRoomCallback','data'=>['clientFd'=>$frame->fd,'serverFd'=>$serverFd]];
                        //退出访客
                        $this->redis->zRem('watchtogether:visitorlist', $frame->fd);
                        //匹配成功user加入redis
                        $now=date("Y-m-d H:i:s");
                        $this->redis->hSet('watchtogether:user:'.$frame->fd, 'fd', $frame->fd);
                        $this->redis->hSet('watchtogether:user:'.$frame->fd, 'roomId', $roomId);
                        $this->redis->hSet('watchtogether:user:'.$frame->fd, 'type', 'joiner');
                        $this->redis->hSet('watchtogether:user:'.$frame->fd, 'createTime', $now);
                        //修改room的jonner
                        $this->redis->hSet('watchtogether:room:'.$roomId, 'joiner', $frame->fd);
                        //修改房间列表人数
                        $this->redis->zAdd('watchtogether:privateroomlist', 2, $roomId);


                    }else{
                        $clientMessageData=['type'=>'joinRoomCallbackError','data'=>'房主已下线!'];
                        // $this->pushMessage($server,$frame->fd,$clientMessageData);
                        $this->leaveRoom($serverFd);
                    }
                }else{
                    $clientMessageData=['type'=>'joinRoomCallbackError','data'=>'观影码错误!'];
                    // $this->pushMessage($server,$frame->fd,$clientMessageData);
                }
            }else{
                $clientMessageData=['type'=>'joinRoomCallbackError','data'=>'房间满员!'];
                // $this->pushMessage($server,$frame->fd,$clientMessageData);
            }
        }else{
            $clientMessageData=['type'=>'joinRoomCallbackError','data'=>'房间不存在!'];
        }
        $this->pushMessage($server,$frame->fd,$clientMessageData);
    }

    public function createOffer($server, $frame){
        var_dump("createOffer");
        $messageData=json_decode($frame->data,true);
        // var_dump($messageData);
        $clientFd=$messageData['clientFd'];
        $clientFd=(int)$clientFd;
        $clientMessageData=['type'=>'clientSetRemoteDescription','data'=>$messageData['data']];
        $clientMessageData=json_encode($clientMessageData);
        $server->push($clientFd, $clientMessageData);
    }
    public function createAnswer($server, $frame){
        var_dump("createAnswer");
        $messageData=json_decode($frame->data,true);
        // var_dump($messageData);
        $serverFd=$messageData['serverFd'];
        $serverFd=(int)$serverFd;
        $serverMessageData=['type'=>'serverSetRemoteDescription','data'=>$messageData['data']];
        $serverMessageData=json_encode($serverMessageData);
        $server->push($serverFd, $serverMessageData);
    }
    public function localIceCandidate($server, $frame){
        var_dump("localIceCandidate");
        $messageData=json_decode($frame->data,true);
        $clientFd=$messageData['clientFd'];
        $clientFd=(int)$clientFd;
        $clientMessageData=['type'=>'clientAddIceCandidate','data'=>$messageData['data']];
        $clientMessageData=json_encode($clientMessageData);
        $server->push($clientFd, $clientMessageData);
    }
    public function remoteIceCandidate($server, $frame){
        var_dump("remoteIceCandidate");
        $messageData=json_decode($frame->data,true);
        $serverFd=$messageData['serverFd'];
        $serverFd=(int)$serverFd;
        $serverMessageData=['type'=>'serverAddIceCandidate','data'=>$messageData['data']];
        $serverMessageData=json_encode($serverMessageData);
        $server->push($serverFd, $serverMessageData);
    }

}

?>
