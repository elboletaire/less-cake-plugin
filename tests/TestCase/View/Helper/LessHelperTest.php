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
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\View\View;

class LessHelperTest extends TestCase
{
    private $Less;

    public function setUp()
    {
        parent::setUp();
        $view = new View();
        $this->Less = new LessHelper($view);
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->removeCacheFiles();
    }

    private function removeCacheFiles()
    {
        $css = scandir(WWW_ROOT . 'css');
        foreach ($css as $file) {
            if (strpos($file, 'lessphp_') === 0) {
                unlink(WWW_ROOT . 'css' . DS . $file);
            }
        }
    }

    public function testFetch()
    {
        // TODO
    }

    public function testLess()
    {
        $options = [
            'cache' => false,
            'parser' => ['sourceMap' => false]
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
        $result = $this->Less->less('less/test.less', ['parser' => ['sourceMap' => false]]);
        $this->assertHtml([
            'link' => [
                'rel' => 'stylesheet',
                'href' => 'preg:/\/css\/lessphp_[0-9a-z]+\.css/'
            ]
        ], $result);

        // Trying the js fallback
        $result = $this->Less->less('less/test_error.less', ['parser' => ['sourceMap' => false]]);
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

    public function testLessDefaultOptions()
    {
        Configure::write('debug', 0);
        $result = $this->Less->less('less/test.less');
        $this->assertHtml([
            'link' => [
                'rel' => 'stylesheet',
                'href' => 'preg:/\/css\/lessphp_[0-9a-z]+\.css/'
            ]
        ], $result);
        $matches = [];
        preg_match('@href="/css/(lessphp_[0-9a-z]+\.css)"@', $result, $matches);
        $result = array_pop($matches);
        $contents = file_get_contents(WWW_ROOT . 'css' . DS . $result);
        $this->assertNotContains('sourceMappingURL=data:application/json,', $contents);
    }

    public function testLessEnablesSourceMap()
    {
        Configure::write('debug', 1);
        $result = $this->Less->less('less/test.less', ['tag' => false]);
        $contents = file_get_contents(WWW_ROOT . 'css' . DS . $result);
        $this->assertContains('sourceMappingURL=data:application/json,', $contents);
    }

    public function testLessWithNotExistingFiles()
    {
        Configure::write('debug', 0);
        $result = $this->Less->less('less/whatever.less');
        $this->assertHtml([
            'link' => [
                'rel' => 'stylesheet/less',
                'href' => '/less/whatever.less'
            ],
            ['script' => true],
            'preg:/\/\/<!\[CDATA\[\s*less\s=\s\{"env":"production"\};\s*\/\/\]\]>\s*/',
            '/script',
            'script' => [
                'src' => '/less/js/less.min.js'
            ]
        ], $result);

        Configure::write('debug', 1);
        $result = $this->Less->less('less/whatever.less');
        $this->assertHtml([
            'link' => [
                'rel' => 'stylesheet/less',
                'href' => '/less/whatever.less'
            ],
            ['script' => true],
            'preg:/\/\/<!\[CDATA\[\s*less\s=\s\{"env":"development"\};\s*\/\/\]\]>\s*/',
            '/script'
        ], $result);
    }

    public function testLessReturnsJsBlockIfDevelopmentEnabled()
    {
        Configure::write('debug', 0);
        $result = $this->Less->less('less/whatever.less', ['js' => ['env' => 'development']]);
        $this->assertHtml([
            'link' => [
                'rel' => 'stylesheet/less',
                'href' => '/less/whatever.less'
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

        $jsBlock = static::getProtectedMethod('jsBlock');
        $result = $jsBlock->invokeArgs($this->Less, ['less/test.less', $options]);
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
            'compress' => true,
            'sourceMap' => false
        ];

        // Basic compiling
        $compile = static::getProtectedMethod('compile');
        $result = $compile->invokeArgs($this->Less, [['less/test.less'], false, $options, []]);
        $this->assertTextEquals('body{background-color: #000}', $result);

        // Changing the bgcolor var
        $result = $compile->invokeArgs($this->Less, [['less/test.less'], false, $options, ['bgcolor' => 'magenta']]);
        $this->assertTextEquals('body{background-color: #f0f}', $result);

        // Compiling plugin file
        $result = $compile->invokeArgs($this->Less, [['Test.less/test.less'], false, $options, []]);
        $this->assertTextEquals('body{background-color: #f0f}', $result);

        // Same but not using plugin notation
        $result = $compile->invokeArgs($this->Less, [['/Test/less/test.less'], false, $options, []]);
        $this->assertTextEquals('body{background-color: #f0f}', $result);

        // Compiling with cache
        $result = $compile->invokeArgs($this->Less, [['less/test.less'], true, $options, []]);
        $this->assertRegExp('/lessphp_[a-z0-9]+\.css/', $result);
        $result = file_get_contents(WWW_ROOT . 'css' . DS . $result);
        $this->assertTextEquals('body{background-color: #000}', $result);

        // Compiling with cache and modify_vars
        $result = $compile->invokeArgs($this->Less, [['less/test.less'], true, $options, ['bgcolor' => 'darkorange']]);
        $this->assertRegExp('/lessphp_[a-z0-9]+\.css/', $result);
        $result = file_get_contents(WWW_ROOT . 'css' . DS . $result);
        $this->assertTextEquals('body{background-color: #ff8c00}', $result);
    }

    public function testAssetBaseUrl()
    {
        $assetBaseUrl = static::getProtectedMethod('assetBaseUrl');
        $result = $assetBaseUrl->invokeArgs($this->Less, [
            'Less',
            'less/styles.less'
        ]);
        $this->assertEquals('/Less/less', $result);

        $result = $assetBaseUrl->invokeArgs($this->Less, [
            'Bootstrap',
            'css/whatever.less'
        ]);
        $this->assertEquals('/Bootstrap/css', $result);

        $result = $assetBaseUrl->invokeArgs($this->Less, [
            'Less',
            'whatever.less'
        ]);
        $this->assertEquals('/Less', $result);
    }

    protected static function getProtectedMethod($name)
    {
        $class = new \ReflectionClass('Less\View\Helper\LessHelper');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
