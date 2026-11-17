"""Feature parity with the web app (PB-13 to PB-15).

The web app's RiskFeatures (PHP) turns a stored financing request into the model's features;
ml.dataset.platform_features turns the same application into features with the code the
model is trained with. Both are checked against one shared fixture, so if either side
changes a definition, one of the two test suites fails.

The PHP side is web/tests/Unit/RiskFeaturesTest.php.
"""

import json
from pathlib import Path

import pandas as pd
import pytest

import ml.dataset as d

FIXTURE = json.loads((Path(__file__).parent / "fixtures" / "feature_parity.json").read_text())
APPLICANTS = FIXTURE["applicants"]


def test_fixture_lists_exactly_the_feature_set_in_order():
    """TC-14-20: every applicant's expected features are the self_declared set, in order."""
    for applicant in APPLICANTS:
        assert tuple(applicant["expected_features"]) == d.FEATURE_SETS[FIXTURE["feature_set"]]


@pytest.mark.parametrize("applicant", APPLICANTS, ids=[a["name"] for a in APPLICANTS])
def test_platform_features_match_the_shared_fixture(applicant):
    """TC-14-21: platform_features() gives the fixture's values (within the tolerance)."""
    row = d.platform_features([applicant["application"]], FIXTURE["feature_set"]).iloc[0]

    for name, expected in applicant["expected_features"].items():
        actual = row[name]
        assert not pd.isna(actual), f"{name} is missing"
        if isinstance(expected, str):
            assert actual == expected, name
        else:
            assert actual == pytest.approx(expected, abs=FIXTURE["tolerance"]), name


def test_every_platform_housing_and_employment_value_is_known_to_the_model():
    """TC-14-23: the web app's housing options and 0-10 years all map to training values."""
    assert set(d.PLATFORM_HOUSING.values()) <= set(d.HOME_OWNERSHIP_GROUPS.values())
    assert sorted(d.PLATFORM_EMP_LENGTH) == list(range(0, 11))
    assert d.emp_length_years(list(d.PLATFORM_EMP_LENGTH.values())).tolist() == list(range(0, 11))


def test_terms_outside_the_training_range_still_become_a_number():
    """TC-14-24: a 12-month request is outside LendingClub's 36/60 but still gives 12.0, not
    missing, so PB-21 can treat term_months as a number."""
    applicant = dict(APPLICANTS[0]["application"], tenure_months=12)
    assert d.platform_features([applicant]).iloc[0]["term_months"] == 12
    assert 12 not in d.TRAINED_TERMS


def test_no_interest_rate_reaches_the_platform_features():
    """TC-14-25: the conversion never reads an interest rate, so Shariah requests score the
    same way as conventional ones."""
    raw = d.platform_raw_frame([APPLICANTS[0]["application"]])
    assert {"int_rate", "installment"}.isdisjoint(raw.columns)
