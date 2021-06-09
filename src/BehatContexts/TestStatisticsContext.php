<?php

declare(strict_types=1);

namespace MinistryOfJustice\BehatContexts;

use Behat\Behat\Context\Context as BehatContext;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Behat\Hook\Scope\StepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\StepNode;
use Carbon\CarbonInterval;

class TestStatisticsContext implements BehatContext
{
    /**
     * @var array<mixed>
     */
    private static array $scenarioTimings = [];

    /**
     * @var array<mixed>
     */
    private static array $stepTimings = [];

    /**
     * @BeforeStep
     * @param BeforeStepScope $scope
     */
    public function startStepTimer(BeforeStepScope $scope): void
    {
        $name = $this->getUniqueStepId($scope);
        self::$stepTimings[$name] = [
            'start' => time(),
            'step' => $scope->getStep(),
            'feature' => $scope->getFeature(),
        ];
    }

    /**
     * @AfterStep
     * @param AfterStepScope $scope
     */
    public function stopStepTimer(AfterStepScope $scope): void
    {
        $end = time();
        $name = $this->getUniqueStepId($scope);
        self::$stepTimings[$name]['end'] = $end;
        self::$stepTimings[$name]['total'] = $end - self::$stepTimings[$name]['start'];
    }

    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function startScenarioTimer(BeforeScenarioScope $scope): void
    {
        $name = $this->getUniqueScenarioId($scope);
        self::$scenarioTimings[$name] = [
            'start' => time(),
            'scenario' => $scope->getScenario(),
            'feature' => $scope->getFeature(),
        ];
    }

    /**
     * @AfterScenario
     * @param AfterScenarioScope $scope
     */
    public function stopScenarioTimer(AfterScenarioScope $scope): void
    {
        $end = time();
        $name = $this->getUniqueScenarioId($scope);
        self::$scenarioTimings[$name]['end'] = $end;
        self::$scenarioTimings[$name]['total'] = $end - self::$scenarioTimings[$name]['start'];
    }

    /**
     * @AfterSuite
     */
    public static function printTimings(): void
    {
        usort(
            self::$scenarioTimings,
            function ($a, $b) {
                if ($a['total'] == $b['total']) {
                    return 0;
                }
                return ($a['total'] > $b['total']) ? -1 : 1;
            }
        );
        usort(
            self::$stepTimings,
            function ($a, $b) {
                if ($a['total'] == $b['total']) {
                    return 0;
                }
                return ($a['total'] > $b['total']) ? -1 : 1;
            }
        );

        echo PHP_EOL . "SLOW SCENARIO TIMINGS (> 10 seconds):" . PHP_EOL;

        foreach (self::$scenarioTimings as $timing) {
            if ($timing['total'] < 10) {
                continue;
            }

            /** @var ScenarioInterface $scenario */
            $scenario = $timing['scenario'];
            /** @var FeatureNode $feature */
            $feature = $timing['feature'];
            $allTags = array_merge($feature->getTags(), $scenario->getTags());

            echo PHP_EOL . $scenario->getTitle() . PHP_EOL;
            echo $feature->getFile() . ':' . $scenario->getLine() . PHP_EOL;
            echo CarbonInterval::seconds($timing['total'])->cascade()->forHumans() . PHP_EOL;
        }

        echo PHP_EOL . PHP_EOL . "SLOW STEP TIMINGS (>4 seconds):" . PHP_EOL;

        foreach (self::$stepTimings as $timing) {
            if ($timing['total'] < 4) {
                continue;
            }

            /** @var StepNode $step */
            $step = $timing['step'];
            /** @var FeatureNode $feature */
            $feature = $timing['feature'];

            echo PHP_EOL . $step->getText() . PHP_EOL;
            echo $feature->getFile() . ':' . $step->getLine() . PHP_EOL;
            echo CarbonInterval::seconds($timing['total'])->cascade()->forHumans() . PHP_EOL;
        }
    }

    public function getUniqueScenarioId(ScenarioScope $scope): string
    {
        return md5(
            $scope->getFeature()->getFile() . $scope->getScenario()->getLine() . $scope->getScenario()->getTitle()
        );
    }

    public function getUniqueStepId(StepScope $scope): string
    {
        return md5(
            $scope->getFeature()->getFile() . $scope->getStep()->getLine() . $scope->getStep()->getText()
        );
    }
}
