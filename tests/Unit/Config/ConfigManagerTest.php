<?php

namespace Terra\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Terra\Config\ConfigManager;

/**
 * Unit tests for ConfigManager
 */
class ConfigManagerTest extends TestCase
{
    public function testDefaultConfiguration()
    {
        $config = new ConfigManager();
        
        $this->assertEquals('tcp://localhost:7889', $config->get('janus.admin_address'));
        $this->assertEquals(30, $config->get('janus.timeout'));
        $this->assertTrue($config->get('logging.enabled'));
    }

    public function testCustomConfiguration()
    {
        $config = new ConfigManager([
            'janus' => [
                'admin_address' => 'tcp://example.com:7889',
                'timeout' => 60,
            ],
        ]);
        
        $this->assertEquals('tcp://example.com:7889', $config->get('janus.admin_address'));
        $this->assertEquals(60, $config->get('janus.timeout'));
    }

    public function testGetWithDefault()
    {
        $config = new ConfigManager();
        
        $this->assertEquals('default', $config->get('nonexistent.key', 'default'));
    }

    public function testSet()
    {
        $config = new ConfigManager();
        
        $config->set('custom.key', 'value');
        $this->assertEquals('value', $config->get('custom.key'));
    }

    public function testHas()
    {
        $config = new ConfigManager();
        
        $this->assertTrue($config->has('janus.admin_address'));
        $this->assertFalse($config->has('nonexistent.key'));
    }

    public function testMerge()
    {
        $config = new ConfigManager([
            'janus' => [
                'admin_address' => 'tcp://localhost:7889',
            ],
        ]);
        
        $config->merge([
            'janus' => [
                'admin_secret' => 'secret',
            ],
        ]);
        
        $this->assertEquals('tcp://localhost:7889', $config->get('janus.admin_address'));
        $this->assertEquals('secret', $config->get('janus.admin_secret'));
    }

    public function testDotNotation()
    {
        $config = new ConfigManager();
        
        $config->set('level1.level2.level3', 'deep value');
        $this->assertEquals('deep value', $config->get('level1.level2.level3'));
    }

    public function testAll()
    {
        $config = new ConfigManager(['test' => 'value']);
        $all = $config->all();
        
        $this->assertIsArray($all);
        $this->assertArrayHasKey('test', $all);
        $this->assertArrayHasKey('janus', $all);
    }
}
