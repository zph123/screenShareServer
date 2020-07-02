<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WebrtcService;

class SwooleWebsocket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swoole:websocket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    public $server;
    public $webrtc;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(WebrtcService $webrtc)
    // public function __construct($webrtc)
    {
        parent::__construct();
        $this->webrtc=$webrtc;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $this->server = new \Swoole\WebSocket\Server("0.0.0.0", env('WEBSOCKET_PORT'),SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
        $this->server->set(array(
            'ssl_cert_file' => env('SSL_CERT_FILE'),
            'ssl_key_file' => env('SSL_KEY_FILE'),
        ));
//        $this->server = new \Swoole\WebSocket\Server("0.0.0.0", 9501);

        $this->server->on('open', function (\swoole_websocket_server $server, $request) {
            echo "fd{$request->fd}进入房间\n";
        });
        $this->server->on('message', function (\Swoole\WebSocket\Server $server, $frame) {
            // $this->getType($server, $frame);
            $messageData=json_decode($frame->data,true);
            if (isset($messageData['type'])) {
                $function = $messageData['type'];
                $this->webrtc->$function($server, $frame);
            }
        });
        $this->server->on('close', function ($server, $fd) {
            // echo "client {$fd} closed\n";
            $this->webrtc->leaveRoom($server,$fd);
        });
        $this->server->start();
    }
}
