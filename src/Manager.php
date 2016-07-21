<?php

namespace EQingdan\SensorsAnalytics;

use DateTime;
use EQingdan\SensorsAnalytics\Consumers\AbstractConsumer;
use EQingdan\SensorsAnalytics\Exceptions\IllegalDataException;
use Exception;

class Manager
{
    const VERSION = '1.5.0';

    private $consumer;
    private $superProperties;

    /**
     * 初始化一个 SensorsAnalytics 的实例用于数据发送。
     *
     * @param AbstractConsumer $consumer
     */
    public function __construct($consumer)
    {
        $this->consumer = $consumer;
        $this->clearSuperProperties();
    }

    /**
     * 跟踪一个用户的行为。
     *
     * @param string $distinctId 用户的唯一标识。
     * @param string $eventName  事件名称。
     * @param array $properties  事件的属性。
     *
     * @return bool
     */
    public function track($distinctId, $eventName, $properties = [])
    {
        if ($properties) {
            $allProperties = array_merge($this->superProperties, $properties);
        } else {
            $allProperties = array_merge($this->superProperties, []);
        }

        return $this->trackEvent('track', $eventName, $distinctId, null, $allProperties);
    }

    /**
     * 这个接口是一个较为复杂的功能，请在使用前先阅读相关说明:http://www.sensorsdata.cn/manual/track_signup.html，并在必要时联系我们的技术支持人员。
     *
     * @param string $distinctId 用户注册之后的唯一标识。
     * @param string $originalId 用户注册前的唯一标识。
     * @param array $properties  事件的属性。
     *
     * @return bool
     * @throws \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     */
    public function trackSignup($distinctId, $originalId, $properties = [])
    {
        if ($properties) {
            $all_properties = array_merge($this->superProperties, $properties);
        } else {
            $all_properties = array_merge($this->superProperties, []);
        }
        // 检查 original_id
        if (!$originalId or strlen($originalId) == 0) {
            throw new IllegalDataException("property [original_id] must not be empty");
        }
        if (strlen($originalId) > 255) {
            throw new IllegalDataException("the max length of [original_id] is 255");
        }

        return $this->trackEvent('track_signup', '$SignUp', $distinctId, $originalId, $all_properties);
    }

    /**
     * 直接设置一个用户的 Profile，如果已存在则覆盖。
     *
     * @param string $distinctId
     * @param array $profiles
     *
     * @return boolean
     */
    public function profileSet($distinctId, $profiles = [])
    {
        return $this->trackEvent('profile_set', null, $distinctId, null, $profiles);
    }

    /**
     * 直接设置一个用户的 Profile，如果某个 Profile 已存在则不设置。
     *
     * @param string $distinctId
     * @param array $profiles
     *
     * @return boolean
     */
    public function profileSetOnce($distinctId, $profiles = array())
    {
        return $this->trackEvent('profile_set_once', null, $distinctId, null, $profiles);
    }

    /**
     * 增减/减少一个用户的某一个或者多个数值类型的 Profile。
     *
     * @param string $distinctId
     * @param array $profiles
     *
     * @return boolean
     */
    public function profileIncrement($distinctId, $profiles = array())
    {
        return $this->trackEvent('profile_increment', null, $distinctId, null, $profiles);
    }

    /**
     * 追加一个用户的某一个或者多个集合类型的 Profile。
     *
     * @param string $distinctId
     * @param array $profiles
     *
     * @return boolean
     */
    public function profileAppend($distinctId, $profiles = array())
    {
        return $this->trackEvent('profile_append', null, $distinctId, null, $profiles);
    }

    /**
     * 删除一个用户的一个或者多个 Profile。
     *
     * @param string $distinctId
     * @param array $profileKeys
     *
     * @return boolean
     */
    public function profileUnset($distinctId, $profileKeys = array())
    {
        if ($profileKeys != null && array_key_exists(0, $profileKeys)) {
            $new_profile_keys = array();
            foreach ($profileKeys as $key) {
                $new_profile_keys[$key] = true;
            }
            $profileKeys = $new_profile_keys;
        }

        return $this->trackEvent('profile_unset', null, $distinctId, null, $profileKeys);
    }


    /**
     * 删除整个用户的信息。
     *
     * @param $distinctId
     *
     * @return boolean
     */
    public function profileDelete($distinctId)
    {
        return $this->trackEvent('profile_delete', null, $distinctId, null, array());
    }

    /**
     * 设置每个事件都带有的一些公共属性
     *
     * @param $superProperties
     */
    public function registerSuperProperties($superProperties)
    {
        $this->superProperties = array_merge($this->superProperties, $superProperties);
    }

    /**
     * 删除所有已设置的事件公共属性
     */
    public function clearSuperProperties()
    {
        $this->superProperties = array(
            '$lib' => 'php',
            '$lib_version' => self::VERSION,
        );
    }

    /**
     * 对于不立即发送数据的 Consumer，调用此接口应当立即进行已有数据的发送。
     */
    public function flush()
    {
        $this->consumer->flush();
    }

    /**
     * 在进程结束或者数据发送完成时，应当调用此接口，以保证所有数据被发送完毕。
     * 如果发生意外，此方法将抛出异常。
     */
    public function close()
    {
        $this->consumer->close();
    }

    /**
     * @param string $updateType
     * @param $eventName
     * @param string $distinctId
     * @param $originalId
     * @param $properties
     *
     * @return bool
     */
    private function trackEvent($updateType, $eventName, $distinctId, $originalId, $properties)
    {
        $eventTime = $this->extractUserTime($properties);

        $data = array(
            'type' => $updateType,
            'properties' => $properties,
            'time' => $eventTime,
            'distinct_id' => $distinctId,
            'lib' => $this->getLibProperties(),
        );

        if (strcmp($updateType, "track") == 0) {
            $data['event'] = $eventName;
        } else if (strcmp($updateType, "track_signup") == 0) {
            $data['event'] = $eventName;
            $data['original_id'] = $originalId;
        }

        $data = $this->normalizeData($data);

        return $this->consumer->send($this->jsonDumps($data));
    }

    private function normalizeData($data)
    {
        // 检查 distinct_id
        if (!isset($data['distinct_id']) or strlen($data['distinct_id']) == 0) {
            throw new IllegalDataException("property [distinct_id] must not be empty");
        }
        if (strlen($data['distinct_id']) > 255) {
            throw new IllegalDataException("the max length of [distinct_id] is 255");
        }
        $data['distinct_id'] = strval($data['distinct_id']);

        // 检查 time
        $ts = (int) ($data['time']);
        $ts_num = strlen($ts);
        if ($ts_num < 10 || $ts_num > 13) {
            throw new IllegalDataException("property [time] must be a timestamp in microseconds");
        }

        if ($ts_num == 10) {
            $ts *= 1000;
        }
        $data['time'] = $ts;

        $name_pattern = "/^((?!^distinct_id$|^original_id$|^time$|^properties$|^id$|^first_id$|^second_id$|^users$|^events$|^event$|^user_id$|^date$|^datetime$)[a-zA-Z_$][a-zA-Z\\d_$]{0,99})$/i";
        // 检查 Event Name
        if (isset($data['event']) && !preg_match($name_pattern, $data['event'])) {
            throw new IllegalDataException("event name must be a valid variable name. [name='${data['event']}']");
        }

        // 检查 properties
        if (isset($data['properties']) && is_array($data['properties'])) {
            foreach ($data['properties'] as $key => $value) {
                if (!is_string($key)) {
                    throw new IllegalDataException("property key must be a str. [key=$key]");
                }
                if (strlen($data['distinct_id']) > 255) {
                    throw new IllegalDataException("the max length of property key is 256. [key=$key]");
                }

                if (!preg_match($name_pattern, $key)) {
                    throw new IllegalDataException("property key must be a valid variable name. [key='$key']]");
                }

                // 只支持简单类型或数组或DateTime类
                if (!is_scalar($value) && !is_array($value) && !$value instanceof DateTime) {
                    throw new IllegalDataException("property value must be a str/int/float/datetime/list. [key='$key' value='value']"); // TODO [key='$key' value='$value']
                }

                // 如果是 DateTime，Format 成字符串
                if ($value instanceof DateTime) {
                    $data['properties'][$key] = $value->format("Y-m-d H:i:s.0");
                }

                if (is_string($value) && strlen($data['distinct_id']) > 8191) {
                    throw new IllegalDataException("the max length of property value is 8191. [key=$key]");
                }

                // 如果是数组，只支持 Value 是字符串格式的简单非关联数组
                if (is_array($value)) {
                    if (array_values($value) !== $value) {
                        throw new IllegalDataException("[list] property must not be associative. [key='$key']");
                    }

                    foreach ($value as $lvalue) {
                        if (!is_string($lvalue)) {
                            throw new IllegalDataException("[list] property's value must be a str. [value='$lvalue']");
                        }
                    }
                }
            }
            // XXX: 解决 PHP 中空 array() 转换成 JSON [] 的问题
            if (count($data['properties']) == 0) {
                $data['properties'] = new \ArrayObject();
            }
        } else {
            throw new IllegalDataException("property must be an array.");
        }

        return $data;
    }

    /**
     * 如果用户传入了 $time 字段，则不使用当前时间。
     *
     * @param array $properties
     *
     * @return int
     */
    private function extractUserTime(&$properties = array())
    {
        if (array_key_exists('$time', $properties)) {
            $time = $properties['$time'];
            unset($properties['$time']);

            return $time;
        }

        return (int) (microtime(true) * 1000);
    }

    /**
     * 返回埋点管理相关属性，由于该函数依赖函数栈信息，因此修改调用关系时，一定要谨慎
     */
    private function getLibProperties()
    {
        $lib_properties = array(
            '$lib' => 'php',
            '$lib_version' => self::VERSION,
            '$lib_method' => 'code',
        );

        if (isset($this->superProperties['$app_version'])) {
            $lib_properties['$app_version'] = $this->superProperties['$app_version'];
        }

        try {
            throw new Exception("");
        } catch (Exception $e) {
            $trace = $e->getTrace();
            if (count($trace) == 3) {
                // 脚本内直接调用
                $file = $trace[2]['file'];
                $line = $trace[2]['line'];

                $lib_properties['$lib_detail'] = "####$file##$line";
            } else if (count($trace > 3)) {
                if (isset($trace[3]['class'])) {
                    // 类成员函数内调用
                    $class = $trace[3]['class'];
                } else {
                    // 全局函数内调用
                    $class = '';
                }

                // XXX: 此处使用 [2] 非笔误，trace 信息就是如此
                $file = $trace[2]['file'];
                $line = $trace[2]['line'];
                $function = $trace[3]['function'];

                $lib_properties['$lib_detail'] = "$class##$function##$file##$line";
            }
        }

        return $lib_properties;
    }

    /**
     * 序列化 JSON
     *
     * @param $data
     *
     * @return string
     */
    private function jsonDumps($data)
    {
        return json_encode($data);
    }

}
