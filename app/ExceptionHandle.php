<?php

namespace app;

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
    /**
     * 不需要记录信息（日志）的异常类列表.
     *
     * @var array
     */
    protected $ignoreReport = [
        /*HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,*/
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）.
     *
     * @param Throwable $exception
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
     * Render an exception into an HTTP response.
     *
     * @param \think\Request $request
     * @param Throwable      $e
     *
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        hook('app_exception', $e);
        // 添加自定义异常处理机制
        // 其他错误交给系统处理
        return parent::render($request, $e);
    }
}
