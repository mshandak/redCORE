<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * Download robo.phar from http://robo.li/robo.phar and type in the root of the repo: $ php robo.phar
 * Or do: $ composer update, and afterwards you will be able to execute robo like $ php vendor/bin/robo
 *
 * @see http://robo.li/
 */
require_once 'vendor/autoload.php';


class RoboFile extends \Robo\Tasks
{
    // load tasks from composer, see composer.json
    use \redcomponent\robo\loadTasks;

    /**
     * Hello World example task.
     *
     * @see  https://github.com/redCOMPONENT-COM/robo/blob/master/src/HelloWorld.php
     * @link https://packagist.org/packages/redcomponent/robo
     *
     * @return object Result
     */
    public function sayHelloWorld()
    {
        $result = $this->taskHelloWorld()->run();

        return $result;
    }

    /**
     * Sends Codeception errors to Slack
     *
     * @param string $slackChannel            The Slack Channel ID
     * @param string $slackToken              Your Slack authentication token.
     * @param string $codeceptionOutputFolder Optional. By default tests/_output
     *
     * @return mixed
     */
    public function sendCodeceptionOutputToSlack($slackChannel, $slackToken = null, $codeceptionOutputFolder = null)
    {
        if (is_null($slackToken)) {
            $this->say('we are in Travis environment, getting token from ENV');

            // Remind to set the token in repo Travis settings,
            // see: http://docs.travis-ci.com/user/environment-variables/#Using-Settings
            $slackToken = getenv('SLACK_ENCRYPTED_TOKEN');
        }

        $result = $this
            ->taskSendCodeceptionOutputToSlack(
                $slackChannel,
                $slackToken,
                $codeceptionOutputFolder
            )
            ->run();

        return $result;
    }

    /**
     * Downloads and prepares a Joomla CMS site for testing
     *
     * @return mixed
     */
    public function prepareSiteForSystemTests()
    {
        // Get Joomla Clean Testing sites
        if (is_dir('tests/joomla-cms3')) {
            $this->taskDeleteDir('tests/joomla-cms3')->run();
        }

        $this->_exec('git clone -b staging --single-branch --depth 1 https://github.com/joomla/joomla-cms.git tests/joomla-cms3');
        $this->say('Joomla CMS site created at tests/joomla-cms3');
    }

    /**
     * Executes Selenium System Tests in your machine
     *
     * @param null $seleniumPath Optional path to selenium-standalone-server-x.jar
     *
     * @return mixed
     */
    public function runTests($seleniumPath = null)
    {
        if (!$seleniumPath) {
            if (!file_exists('selenium-server-standalone.jar')) {
                $this->say('Downloading Selenium Server, this may take a while.');
                $this->taskExec('wget')
                    ->arg('http://selenium-release.storage.googleapis.com/2.45/selenium-server-standalone-2.45.0.jar')
                    ->arg('-O selenium-server-standalone.jar')
                    ->printed(false)
                    ->run();
            }
            $seleniumPath = 'selenium-server-standalone.jar';
        }

        // running Selenium server in background
        $this->taskExec('java -jar ' . $seleniumPath)
            ->background()
            ->run();

        // Make sure we have Composer
        if (!file_exists('./composer.phar')) {
            $this->_exec('curl -sS https://getcomposer.org/installer | php');
        }

        $this->taskComposerUpdate()->run();
        
        // Loading Symfony Command and running with passed argument
        $this->taskCodecept()->getCommand('build');

        $this->taskCodecept()
            ->suite('acceptance')
            ->arg('--steps')
            ->arg('--debug')
            ->run();

        // Kill selenium server
        //$this->_exec('curl http://localhost:4444/selenium-server/driver/?cmd=shutDownSeleniumServer');
    }
}