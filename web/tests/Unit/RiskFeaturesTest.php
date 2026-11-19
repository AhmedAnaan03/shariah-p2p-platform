<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\CampaignFinancials;
use App\Support\RiskFeatures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PB-14: the structured fields become the risk model's features, with the same names, order
 * and values as the training code. The value check reads the fixture that
 * risk-service/tests/test_feature_parity.py also reads, so PHP and Python must agree.
 */
class RiskFeaturesTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../../../risk-service/tests/fixtures/feature_parity.json';

    /**
     * risk-service/ml/dataset.py FEATURE_SETS['self_declared'], copied here on purpose: if the
     * Python set changes, this test fails until RiskFeatures is updated too.
     */
    private const PYTHON_SELF_DECLARED = [
        'term_months', 'purpose_group', 'loan_to_income', 'repayment_burden', 'dti',
        'emp_length_years', 'home_ownership', 'open_acc', 'delinq_2yrs', 'pub_rec',
    ];

    private static function fixture(): array
    {
        return json_decode(file_get_contents(self::FIXTURE), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function applicants(): array
    {
        $cases = [];
        foreach (self::fixture()['applicants'] as $applicant) {
            $cases[$applicant['name']] = [$applicant['application'], $applicant['expected_features']];
        }

        return $cases;
    }

    public function test_feature_names_and_order_match_the_python_feature_set(): void
    {
        $this->assertSame(self::PYTHON_SELF_DECLARED, RiskFeatures::NAMES);
        $this->assertSame('self_declared', self::fixture()['feature_set']);

        foreach (self::fixture()['applicants'] as $applicant) {
            $this->assertSame(RiskFeatures::NAMES, array_keys($applicant['expected_features']));
            $this->assertSame(RiskFeatures::NAMES, array_keys(RiskFeatures::fromValues($applicant['application'])));
        }
    }

    #[DataProvider('applicants')]
    public function test_values_match_the_shared_fixture(array $application, array $expected): void
    {
        $actual = RiskFeatures::fromValues($application);
        $tolerance = self::fixture()['tolerance'];

        foreach ($expected as $name => $value) {
            if (is_string($value)) {
                $this->assertSame($value, $actual[$name], $name);
            } else {
                $this->assertEqualsWithDelta($value, $actual[$name], $tolerance, $name);
            }
        }
    }

    public function test_for_reads_the_request_and_its_financials(): void
    {
        $fixture = self::fixture()['applicants'][0];
        $application = $fixture['application'];

        $campaign = new Campaign([
            'amount_requested' => $application['amount_requested'],
            'tenure_months' => $application['tenure_months'],
            'purpose' => $application['purpose'],
        ]);
        $campaign->setRelation('financials', new CampaignFinancials($application));

        $this->assertEquals(RiskFeatures::fromValues($application), RiskFeatures::for($campaign));
    }

    public function test_every_form_option_has_a_model_value(): void
    {
        $this->assertSame(array_keys(Campaign::PURPOSES), array_keys(RiskFeatures::PURPOSE_GROUPS));
        $this->assertSame(array_keys(CampaignFinancials::HOUSING), array_keys(RiskFeatures::HOME_OWNERSHIP));
    }

    public function test_the_same_request_scores_the_same_whatever_the_contract(): void
    {
        // No contract term is an input, so a Murabahah and a conventional request with the
        // same facts get the same features (no interest rate can reach the model).
        $application = self::fixture()['applicants'][0]['application'];

        $this->assertEquals(
            RiskFeatures::fromValues($application),
            RiskFeatures::fromValues($application + ['contract_type' => 'murabahah', 'interest_rate_percent' => '12.00']),
        );
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function tenures(): array
    {
        return [
            '3 months' => [3, true],
            '12 months' => [12, true],
            '36 months' => [36, false],
            '48 months' => [48, true],
            '60 months' => [60, false],
        ];
    }

    #[DataProvider('tenures')]
    public function test_tenures_other_than_36_or_60_are_flagged_as_outside_the_training_range(int $tenure, bool $outside): void
    {
        $this->assertSame($outside, RiskFeatures::tenureOutsideTrainingRange($tenure));
    }
}
