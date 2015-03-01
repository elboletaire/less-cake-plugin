<?php
/**
 * Test file for Less Helper
 *
 * @author Òscar Casajuana <elboletaire@underave.net>
 * @license Apache-2.0
 * @copyright Òscar Casajuana 2013-2015
 */
namespace Less\Test\TestCase\View\Helper;

use Less\View\Helper\LessHelper;
use Cake\TestSuite\TestCase;
use Cake\View\View;

class LessHelperTest extends TestCase
{
    private $Less;

    public function setUp()
    {
        parent::setUp();
        $View = new View();
        $this->Less = new LessHelper($View);
    }

    public function testFetch()
    {
        // TODO
    }

    public function testLess()
    {
        $options = [
            'cache' => false
        ];

        // [Less.php] Basic compiling
        $result = $this->Less->less('less/test.less', $options, ['bgcolor' => 'red']);
        $this->assertHtml([
            ['style' => true],
            'body{background-color: #f00}',
            '/style'
        ], $result);

        // [Less.php] Compiling using cache (here we only check for the
        // resulting tag, as the compilation checks are made in testCompile)
        $result = $this->Less->less('less/test.less');
        $this->assertHtml([
            'link' => [
                'rel' => 'stylesheet',
                'href' => 'preg:/\/css\/lessphp_[0-9a-z]+\.css/'
            ]
        ], $result);

        // Trying the js fallback
        $result = $this->Less->less('less/test_error.less');
        $this->assertHtml([
            'link' => [
                'rel' => 'stylesheet/less',
                'href' => '/less/test_error.less'
            ],
            ['script' => true],
            'preg:/\/\/<!\[CDATA\[\s*less\s=\s\{"env":"development"\};\s*\/\/\]\]>\s*/',
            '/script',
            'script' => [
                'src' => '/less/js/less.min.js'
            ]
        ], $result);
    }

    public function testJsBlock()
    {
        $options = [
            'less' => 'less.empty.js',
            'js'   => []
        ];

        $result = $this->Less->jsBlock('less/test.less', $options);
        $this->assertHtml([
            'link' => [
                'rel' => 'stylesheet/less',
                'href' => '/less/test.less'
            ],
            ['script' => true],
            'preg:/\/\/<!\[CDATA\[\s*less\s=\s\[\];\s*\/\/\]\]>\s*/',
            '/script',
            'script' => [
                'src' => '/js/less.empty.js'
            ]
        ], $result);
    }

    public function testCompile()
    {
        $options = [
            'compress' => true
        ];

        // Basic compiling
        $result = $this->Less->compile(['less/test.less'], $options, [], false);
        $this->assertTextEquals('body{background-color: #000}', $result);

        // Changing the bgcolor var
        $result = $this->Less->compile(['less/test.less'], $options, ['bgcolor' => 'magenta'], false);
        $this->assertTextEquals('body{background-color: #f0f}', $result);

        // Compiling plugin file
        $result = $this->Less->compile(['Test.less/test.less'], $options, [], false);
        $this->assertTextEquals('body{background-color: #f0f}', $result);

        // Same but not using plugin notation
        $result = $this->Less->compile(['/Test/less/test.less'], $options, [], false);
        $this->assertTextEquals('body{background-color: #f0f}', $result);

        // Compiling with cache
        $result = $this->Less->compile(['less/test.less'], $options);
        $this->assertRegExp('/lessphp_[a-z0-9]+\.css/', $result);
        $result = file_get_contents(WWW_ROOT . 'css' . DS . $result);
        $this->assertTextEquals('body{background-color: #000}', $result);

        // Compiling with cache and modify_vars
        $result = $this->Less->compile(['less/test.less'], $options, ['bgcolor' => 'darkorange']);
        $this->assertRegExp('/lessphp_[a-z0-9]+\.css/', $result);
        $result = file_get_contents(WWW_ROOT . 'css' . DS . $result);
        $this->assertTextEquals('body{background-color: #ff8c00}', $result);
    }

    public function testAssetBaseUrl()
    {
        $assetBaseUrl = self::getProtectedMethod('assetBaseUrl');
        $result = $assetBaseUrl->invokeArgs($this->Less, [
            'Less',
            'less/styles.less'
        ]);
        $this->assertEquals('http://localhost/Less/less', $result);

        $result = $assetBaseUrl->invokeArgs($this->Less, [
            'Bootstrap',
            'css/whatever.less'
        ]);
        $this->assertEquals('http://localhost/Bootstrap/css', $result);

        $result = $assetBaseUrl->invokeArgs($this->Less, [
            'Less',
            'whatever.less'
        ]);
        $this->assertEquals('http://localhost/Less', $result);
    }

    protected static function getProtectedMethod($name)
    {
        $class = new \ReflectionClass('Less\View\Helper\LessHelper');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
