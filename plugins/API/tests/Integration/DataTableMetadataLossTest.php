<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\API\tests\Integration;

use Piwik\DataTable;
use Piwik\DataTable\Map;
use Piwik\Plugins\API\API;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group API
 * @group Plugins
 */
class DataTableMetadataLossTest extends IntegrationTestCase
{
    /**
     * @var int
     */
    private $idSite;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser(true);
        $this->idSite = Fixture::createWebsite('2015-01-01 00:00:00');

        $trackerDayOne = Fixture::getTracker($this->idSite, '2015-01-02 10:00:00');
        Fixture::checkResponse($trackerDayOne->doTrackPageView('/page-one'));
        Fixture::checkResponse($trackerDayOne->doTrackPageView('/page-two'));

        $trackerDayTwo = Fixture::getTracker($this->idSite, '2015-01-03 10:00:00');
        Fixture::checkResponse($trackerDayTwo->doTrackPageView('/page-one'));
        Fixture::checkResponse($trackerDayTwo->doTrackPageView('/page-three'));
    }

    public function testSinglePeriodProcessedReportDoesNotExposeTotalRowsBeforeLimitMetadata(): void
    {
        $processed = $this->callProcessedReport('day', '2015-01-02');
        $reportData = $processed['reportData'] ?? null;

        self::assertInstanceOf(DataTable::class, $reportData);
        self::assertFalse($reportData->getMetadata(DataTable::TOTAL_ROWS_BEFORE_LIMIT_METADATA_NAME));
    }

    public function testMultiPeriodProcessedReportPreservesTotalRowsBeforeLimitMetadataOnInnerTables(): void
    {
        $processed = $this->callProcessedReport('day', '2015-01-02,2015-01-03');
        $reportData = $processed['reportData'] ?? null;

        self::assertInstanceOf(Map::class, $reportData);

        $found = false;

        foreach ($reportData->getDataTables() as $table) {
            $metadataValue = $table->getMetadata(DataTable::TOTAL_ROWS_BEFORE_LIMIT_METADATA_NAME);
            if ($metadataValue === false) {
                continue;
            }

            self::assertIsInt($metadataValue);
            self::assertGreaterThanOrEqual(0, $metadataValue);
            $found = true;
        }

        self::assertTrue($found);
    }

    /**
     * @return array<string, mixed>
     */
    private function callProcessedReport(string $period, string $date): array
    {
        $previousGet = $_GET;
        $_GET['filter_limit'] = 1;
        $_GET['filter_offset'] = 0;

        try {
            $result = API::getInstance()->getProcessedReport(
                $this->idSite,
                $period,
                $date,
                'Actions',
                'getPageUrls',
                false,
                [],
                false,
                false,
                false,
                true,
                false,
                false,
                null,
                false
            );
        } finally {
            $_GET = $previousGet;
        }

        self::assertIsArray($result);
        return $result;
    }
}
