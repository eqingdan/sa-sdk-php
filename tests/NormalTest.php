<?php

use EQingdan\SensorsAnalytics\Consumers\BatchConsumer;
use EQingdan\SensorsAnalytics\Consumers\DebugConsumer;
use EQingdan\SensorsAnalytics\Consumers\FileConsumer;
use EQingdan\SensorsAnalytics\Manager;

class NormalTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
    }

    public function tearDown()
    {
    }

    public function testFileConsumer()
    {
        $test_file = "php_sdk_test";
        $consumer = new FileConsumer($test_file);
        $sa = new Manager($consumer);
        $now = (int) (microtime(true) * 1000);
        $sa->track('1234', 'Test', array('From' => 'Baidu', '$time' => $now));
        $sa->trackSignup('1234', 'abcd', array('Channel' => 'Hongbao'));
        $sa->profileDelete('1234');
        $sa->profileAppend('1234', array('Gender' => 'Male'));
        $sa->profileIncrement('1234', array('CardNum' => 1));
        $sa->profileSet('1234', array('City' => '北京'));
        $sa->profileUnset('1234', array('City'));
        $sa->profileUnset('1234', array('Province' => true));
        $dt = new DateTime();
        $dt->setTimestamp($now / 1000.0);
        $sa->profileSet('1234', array('$signup_time' => $dt));
        $file_contents = file_get_contents($test_file);

        $list = explode("\n", $file_contents);
        $i = 0;
        foreach ($list as $key => $item) {
            if (strlen($item) > 0) {
                $i ++;
                $list[$key] = json_decode($item, true);
            }
        }
        unlink($test_file);
        $this->assertEquals($now, $list[0]['time']);
        $this->assertArrayNotHasKey('time', $list[0]['properties']);
        $this->assertTrue(microtime(true) * 1000 - $list[1]['time'] < 1000);
        $this->assertTrue($list[6]['properties']['City'] === true);
        $this->assertTrue($list[7]['properties']['Province'] === true);
        $this->assertEquals($i, 9);
    }


    function my_gzdecode($string)
    {
        $string = substr($string, 10);

        return gzinflate($string);
    }

    function _mock_do_request($msg)
    {
        $data = json_decode($this->my_gzdecode(base64_decode($msg['data_list'])));
        $this->_msg_count += count($data);

        return true;
    }

    public function testNormal()
    {
        $stub_consumer = $this->getMockBuilder(\EQingdan\SensorsAnalytics\Consumers\BatchConsumer::class)
            ->setConstructorArgs(array(""))
            ->setMethods(array("doRequest"))
            ->getMock();
        $stub_consumer->method('doRequest')->will($this->returnCallback(array($this, '_mock_do_request')));
        $sa = new Manager($stub_consumer);
        $this->_msg_count = 0;
        $sa->track(1234, 'Test', array('From' => 'Baidu'));
        $sa->track(1234, 'Test', array('From' => 'Baidu', '$time' => 1437816376));
        $sa->track(1234, 'Test', array('From' => 'Baidu', '$time' => 1437816376000));
        $sa->track(1234, 'Test', array('From' => 'Baidu', '$time' => '1437816376'));
        $sa->track(1234, 'Test', array('From' => 'Baidu', '$time' => '1437816376000'));
        $sa->track(1234, 'Tes123_$t', array('From' => 'Baidu', '$time' => '1437816376000'));
        $sa->track(1234, 'Tes123_$t',
            array('From' => 'Baidu', '$time' => '1437816376000', 'Test' => 1437816376000999933));
        $sa->profileSet(1234, array('From' => 'Baidu'));
        $sa->profileSet(1234, array('From' => 'Baidu', 'asd' => array("asd", "bbb")));
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*property \[distinct_id\] must not be empty.*#
     */
    public function testException1()
    {
        $sa = new Manager(null);
        $sa->track(null, 'test', array('from' => 'baidu'));
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*must be a timestamp in microseconds.*#
     */
    public function testException2()
    {
        $sa = new Manager(null);
        $sa->track(1234, 'Test', array('From' => 'Baidu', '$time' => 1234));
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*property key must be a str.*#
     */
    public function testException3()
    {
        $sa = new Manager(null);
        $sa->track(1234, 'Test', array(123 => 'Baidu'));
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*event name must be a valid variable nam.*#
     */
    public function testException4()
    {
        $sa = new Manager(null);
        $sa->track(1234, 'Test 123', array(123 => 'Baidu'));
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*property value must be a str.*#
     */
    public function testException5()
    {
        $sa = new Manager(null);
        $sa->track(1234, 'TestEvent', [
            'TestProperty' => new Manager(null),
        ]);
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*property key must be a valid variable name.*#
     */
    public function testException6()
    {
        $sa = new Manager(null);
        $sa->track(1234, 'Test', array('distincT_id' => 'SensorsData'));
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*property key must be a valid variable name.*#
     */
    public function testException7()
    {
        $sa = new Manager(null);
        $sa->track(1234, 'TestEvent',
            array('a123456789a123456789a123456789a123456789a123456789a123456789a123456789a123456789a123456789a1234567890' => 'SensorsData'));
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*property's value must be a str.*#
     */
    public function testException8()
    {
        $sa = new Manager(null);
        $sa->track(1234, 'TestEvent', array('TestProperty' => array(123)));
    }

    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #.*property must not be associative.*#
     */
    public function testException9()
    {
        $sa = new Manager(null);
        $a = array("b" => 123);
        $c = array(123);
        $sa->track(1234, 'TestEvent', array('TestProperty' => array("a" => 123)));
    }


    /**
     * @expectedException    \EQingdan\SensorsAnalytics\Exceptions\IllegalDataException
     * @expectedExceptionMessageRegExp #the max length of \[distinct_id\] is 255#
     */
    public function testException10()
    {
        $sa = new Manager(null);
        $sa->track('abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz',
            'TestEvent', array('test_key' => 'SensorsData'));
    }

    public function testBatchConsumerMock()
    {
        $stub_consumer = $this->getMockBuilder(\EQingdan\SensorsAnalytics\Consumers\BatchConsumer::class)
            ->setConstructorArgs(array(""))
            ->setMethods(array("doRequest"))
            ->getMock();
        $stub_consumer->method('doRequest')->will($this->returnCallback(array($this, '_mock_do_request')));

        $sa = new Manager($stub_consumer);
        $this->_msg_count = 0;
        $sa->track('1234', 'Test', array('From' => 'Baidu'));
        $sa->trackSignup('1234', 'abcd', array('Channel' => 'Hongbao'));
        $sa->profileDelete('1234');
        $sa->profileAppend('1234', array('Gender' => 'Male'));
        $sa->profileIncrement('1234', array('CardNum' => 1));
        $sa->profileSet('1234', array('City' => '北京'));
        $sa->profileUnset('1234', array('City'));
        $this->assertEquals($this->_msg_count, 0);
        $sa->flush();
        $this->assertEquals($this->_msg_count, 7);
        for ($i = 0; $i < 49; $i ++) {
            $sa->profileSet('1234', array('City' => '北京'));
        }
        $this->assertEquals($this->_msg_count, 7);
        $sa->profileSet('1234', array('City' => '北京'));
        $this->assertEquals($this->_msg_count, 57);
    }

    public function testBatchConsumer()
    {
        $consumer = new BatchConsumer("http://git.sensorsdata.cn/test");
        $sa = new Manager($consumer);
        $sa->track('1234', 'Test', array('From' => 'Baidu'));
        $sa->trackSignup('1234', 'abcd', array('Channel' => 'Hongbao'));
        $sa->profileDelete('1234');
        $sa->profileAppend('1234', array('Gender' => 'Male'));
        $sa->profileIncrement('1234', array('CardNum' => 1));
        $sa->profileSet('1234', array('City' => '北京'));
        $sa->profileUnset('1234', array('City'));
        $sa->flush();
        for ($i = 0; $i < 49; $i ++) {
            $sa->profileSet('1234', array('City' => '北京'));
        }
        $sa->profileSet('1234', array('City' => '北京'));
        $sa->close();
    }

    // TODO 该测试用例为内网地址, 暂时屏蔽
    public function testDebugConsumer()
    {
//        $consumer = new DebugConsumer('http://10.10.229.134:8001/debug', false);
//        $sa = new Manager($consumer);
//        $sa->track('1234', 'Test', array('PhpTestProperty' => 'Baidu'));
//        $consumer = new DebugConsumer('http://10.10.229.134:8001/debug', true);
//        $sa = new Manager($consumer);
//        $sa->track('1234', 'Test', array('PhpTestProperty' => 123));
//        $sa->track('1234', 'Test', array('PhpTestProperty' => 'Baidu'));
    }
}
