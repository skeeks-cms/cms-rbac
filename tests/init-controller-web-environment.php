<?php

$environment = $argv[1] ?? 'test';

defined('ROOT_DIR') or define('ROOT_DIR', getcwd());
putenv('ENV=' . $environment);
require ROOT_DIR . '/vendor/skeeks/cms/bootstrap.php';

final class TestInitController extends \skeeks\cms\rbac\console\controllers\InitController
{
    public function resolveWebConfigGroups(string $environment): array
    {
        $configPaths = new \Yiisoft\Config\ConfigPaths(ROOT_DIR, 'config');
        $configProbe = new \Yiisoft\Config\Config($configPaths, null, [], null);

        return $this->_resolveWebConfigGroups($configProbe, $environment);
    }

    public function createWebApplication(string $environment): \yii\web\Application
    {
        return $this->_createWebApplication($environment);
    }
}

$consoleApplication = new \yii\console\Application([
    'id' => 'cms-rbac-web-environment-test',
    'basePath' => ROOT_DIR,
]);
$controller = new TestInitController('init', $consoleApplication);
$expectedEnvironment = in_array($environment, ['dev', 'prod'], true) ? $environment : 'prod';
$expectedGroups = ['web-' . $expectedEnvironment, 'params-web-' . $expectedEnvironment];
$actualGroups = $controller->resolveWebConfigGroups($environment);

if ($actualGroups !== $expectedGroups) {
    throw new \RuntimeException(
        'Unexpected config groups: ' . implode(', ', $actualGroups)
    );
}

$webApplication = $controller->createWebApplication($environment);

if (!$webApplication instanceof \yii\web\Application) {
    throw new \RuntimeException('Web application was not created.');
}

echo $environment . ': ' . count($webApplication->getComponents(true)) . " components\n";
