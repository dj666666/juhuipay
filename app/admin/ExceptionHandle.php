<?php

namespace app\admin;

use app\common\library\traits\Jump;
use Throwable;
use think\Response;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\exception\HttpResponseException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;

/**
 * 应用异常处理类.
 */
class ExceptionHandle extends Handle
{
    use Jump;
    /**
     * 不需要记录信息（日志）的异常类列表.
     *
     * @var array
     */
    protected $ignoreReport = [
        // HttpException::class,
         HttpResponseException::class,
        // ModelNotFoundException::class,
        // DataNotFoundException::class,
        // ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）.
     *
     * @param  Throwable  $exception
     *
     * @return void
     */
    public function report(Throwable $exception): void
    {
        hook('app_exception_report', ['exception'=>$exception,'ignoreReport'=>$this->ignoreReport]);
        // 使用内置的方式记录异常日志
        //parent::report($exception);
        
        if (!$this->isIgnoreReport($exception)) {
            // 收集异常数据
            if ($this->app->isDebug()) {
                $data = [
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                    'message' => $this->getMessage($exception),
                    'code'    => $this->getCode($exception),
                    'ip'      => request()->ip(),
                ];
                $log = "[{$data['ip']}]{$data['message']}[{$data['file']}:{$data['line']}]";
            } else {
                $data = [
                    'code'    => $this->getCode($exception),
                    'message' => $this->getMessage($exception),
                    'ip'      => request()->ip(),
                ];
                $log = "[{$data['ip']}][{$data['code']}]{$data['message']}";
            }

            if ($this->app->config->get('log.record_trace')) {
                $log .= PHP_EOL . $exception->getTraceAsString();
            }

            try {
                $this->app->log->record($log, 'error');
            } catch (Exception $e) {

            }
        }
        
    }

    /**
     * @param  \think\Request  $request
     * @param  Throwable       $e
     *
     * @return Response
     * @throws \Exception
     */
    public function render($request, Throwable $e): Response
    {
        // 参数验证错误
        if ($e instanceof ValidateException) {
            $this->error($e->getMessage(), '', '', 3, ['__token__' => $request->buildToken()]);
        }
        hook('admin_exception', $e);
        // 添加自定义异常处理机制
        
        $data = [
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'message' => $this->getMessage($e),
            'code'    => $this->getCode($e),
            'ip'      => request()->ip(),
        ];
        event('SysErrorLog', $data);
        
        // 其他错误交给系统处理
        return parent::render($request, $e);
    }
}
