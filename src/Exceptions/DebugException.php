<?php

namespace EQingdan\SensorsAnalytics\Exceptions;

/**
 * 当且仅当DEBUG模式中，任何网络错误、数据异常等都会抛出此异常，用户可不捕获，用于测试SDK接入正确性
 */
class DebugException extends Exception
{
    // ...
}
