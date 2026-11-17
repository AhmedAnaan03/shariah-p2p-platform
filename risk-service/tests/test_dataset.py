"""Tests for the dataset rules in ml/dataset.py (PB-12).

Each test uses a few hand-made rows, so the expected answer can be checked by eye and
the tests run in CI without the 1.6 GB download.
"""

import math

import pandas as pd
import pytest

from ml import dataset as d

# The 151 columns of accepted_2007_to_2018Q4.csv, in file order.
RAW_HEADER = """
id member_id loan_amnt funded_amnt funded_amnt_inv term int_rate installment grade sub_grade
emp_title emp_length home_ownership annual_inc verification_status issue_d loan_status
pymnt_plan url desc purpose title zip_code addr_state dti delinq_2yrs earliest_cr_line
fico_range_low fico_range_high inq_last_6mths mths_since_last_delinq mths_since_last_record
open_acc pub_rec revol_bal revol_util total_acc initial_list_status out_prncp out_prncp_inv
total_pymnt total_pymnt_inv total_rec_prncp total_rec_int total_rec_late_fee recoveries
collection_recovery_fee last_pymnt_d last_pymnt_amnt next_pymnt_d last_credit_pull_d
last_fico_range_high last_fico_range_low collections_12_mths_ex_med mths_since_last_major_derog
policy_code application_type annual_inc_joint dti_joint verification_status_joint acc_now_delinq
tot_coll_amt tot_cur_bal open_acc_6m open_act_il open_il_12m open_il_24m mths_since_rcnt_il
total_bal_il il_util open_rv_12m open_rv_24m max_bal_bc all_util total_rev_hi_lim inq_fi
total_cu_tl inq_last_12m acc_open_past_24mths avg_cur_bal bc_open_to_buy bc_util
chargeoff_within_12_mths delinq_amnt mo_sin_old_il_acct mo_sin_old_rev_tl_op
mo_sin_rcnt_rev_tl_op mo_sin_rcnt_tl mort_acc mths_since_recent_bc mths_since_recent_bc_dlq
mths_since_recent_inq mths_since_recent_revol_delinq num_accts_ever_120_pd num_actv_bc_tl
num_actv_rev_tl num_bc_sats num_bc_tl num_il_tl num_op_rev_tl num_rev_accts
num_rev_tl_bal_gt_0 num_sats num_tl_120dpd_2m num_tl_30dpd num_tl_90g_dpd_24m
num_tl_op_past_12m pct_tl_nvr_dlq percent_bc_gt_75 pub_rec_bankruptcies tax_liens
tot_hi_cred_lim total_bal_ex_mort total_bc_limit total_il_high_credit_limit revol_bal_joint
sec_app_fico_range_low sec_app_fico_range_high sec_app_earliest_cr_line sec_app_inq_last_6mths
sec_app_mort_acc sec_app_open_acc sec_app_revol_util sec_app_open_act_il sec_app_num_rev_accts
sec_app_chargeoff_within_12_mths sec_app_collections_12_mths_ex_med
sec_app_mths_since_last_major_derog hardship_flag hardship_type hardship_reason hardship_status
deferral_term hardship_amount hardship_start_date hardship_end_date payment_plan_start_date
hardship_length hardship_dpd hardship_loan_status orig_projected_additional_accrued_interest
hardship_payoff_balance_amount hardship_last_payment_amount disbursement_method
debt_settlement_flag debt_settlement_flag_date settlement_status settlement_date
settlement_amount settlement_percentage settlement_term
""".split()


def loan(**overrides):
    """One valid raw LendingClub row; tests override the fields they care about."""
    row = {
        "loan_status": "Fully Paid", "issue_d": "Jan-2014", "term": " 36 months",
        "application_type": "Individual", "loan_amnt": 12000.0, "annual_inc": 60000.0,
        "dti": 15.0, "emp_length": "5 years", "home_ownership": "RENT",
        "purpose": "debt_consolidation", "fico_range_low": 690.0, "fico_range_high": 694.0,
        "earliest_cr_line": "Jan-2004", "delinq_2yrs": 0.0, "inq_last_6mths": 1.0,
        "open_acc": 8.0, "total_acc": 20.0, "revol_util": 45.0, "pub_rec": 0.0,
    }
    row.update(overrides)
    return row


SNAPSHOT = pd.Timestamp("2019-03-01")


# --- Label --------------------------------------------------------------------------


@pytest.mark.parametrize(
    "status, expected",
    [
        ("Fully Paid", 0),
        ("Charged Off", 1),
        ("Default", 1),
        ("Does not meet the credit policy. Status:Fully Paid", 0),
        ("Does not meet the credit policy. Status:Charged Off", 1),
    ],
)
def test_final_statuses_get_a_label(status, expected):
    assert d.default_label([status]).iloc[0] == expected


@pytest.mark.parametrize("status", ["Current", "In Grace Period", "Late (31-120 days)", "", None, "fully paid"])
def test_unfinished_blank_or_unknown_statuses_get_no_label(status):
    assert pd.isna(d.default_label([status]).iloc[0])


# --- Parsing helpers ----------------------------------------------------------------


@pytest.mark.parametrize("text, expected", [(" 36 months", 36), ("60 months", 60), ("1 month", 1)])
def test_term_months_valid(text, expected):
    assert d.term_months([text]).iloc[0] == expected


@pytest.mark.parametrize("text", ["", None, "36", "three years", "-36 months"])
def test_term_months_blank_or_invalid_is_missing(text):
    assert math.isnan(d.term_months([text]).iloc[0])


@pytest.mark.parametrize("text, expected", [("< 1 year", 0), ("1 year", 1), ("7 years", 7), ("10+ years", 10)])
def test_emp_length_valid(text, expected):
    assert d.emp_length_years([text]).iloc[0] == expected


@pytest.mark.parametrize("text", ["", None, "n/a", "11 years", "ten years"])
def test_emp_length_blank_or_invalid_is_missing(text):
    assert math.isnan(d.emp_length_years([text]).iloc[0])


def test_parse_month_valid_blank_and_invalid():
    parsed = d.parse_month(["Aug-2003", "", None, "2003-08"])

    assert parsed.iloc[0] == pd.Timestamp("2003-08-01")
    assert parsed.iloc[1:].isna().all()


def test_months_between_counts_calendar_months_and_rejects_negative():
    start = d.parse_month(["Jan-2004", "Mar-2015", None])
    end = d.parse_month(["Jan-2014", "Jan-2014", "Jan-2014"])

    assert d.months_between(start, end).iloc[0] == 120
    assert math.isnan(d.months_between(start, end).iloc[1])  # first credit line after the loan
    assert math.isnan(d.months_between(start, end).iloc[2])  # blank date


@pytest.mark.parametrize("denominator", [0.0, -5.0, float("nan")])
def test_safe_ratio_is_missing_when_denominator_is_not_positive(denominator):
    assert math.isnan(d.safe_ratio([10.0], [denominator]).iloc[0])


def test_safe_ratio_valid():
    assert d.safe_ratio([12000.0], [60000.0]).iloc[0] == pytest.approx(0.2)


# --- Category mappings --------------------------------------------------------------


@pytest.mark.parametrize(
    "purpose, expected",
    [("small_business", "business"), ("educational", "education"), ("debt_consolidation", "personal"), ("wedding", "personal")],
)
def test_lendingclub_purposes_map_to_three_groups(purpose, expected):
    assert d.purpose_group([purpose]).iloc[0] == expected


@pytest.mark.parametrize(
    "purpose, expected",
    [("start-up", "business"), ("growth", "business"), ("education", "education"), ("personal", "personal")],
)
def test_platform_purposes_map_to_the_same_groups(purpose, expected):
    """Our PB-13 form's options must land in the vocabulary the model was trained on."""
    assert d.purpose_group([purpose], mapping=d.PLATFORM_PURPOSES).iloc[0] == expected


def test_every_platform_and_lendingclub_purpose_group_is_shared():
    assert set(d.PLATFORM_PURPOSES.values()) == set(d.LENDINGCLUB_PURPOSES.values())


@pytest.mark.parametrize("purpose", ["", None, "holiday"])
def test_blank_or_unknown_purpose_is_missing(purpose):
    assert pd.isna(d.purpose_group([purpose]).iloc[0])


@pytest.mark.parametrize("value, expected", [("RENT", "RENT"), ("mortgage", "MORTGAGE"), ("ANY", "OTHER"), ("NONE", "OTHER")])
def test_home_ownership_groups(value, expected):
    assert d.home_ownership_group([value]).iloc[0] == expected


@pytest.mark.parametrize("value", ["", None, "CASTLE"])
def test_blank_or_unknown_home_ownership_is_missing(value):
    assert pd.isna(d.home_ownership_group([value]).iloc[0])


# --- Modelling population -----------------------------------------------------------


def test_snapshot_is_latest_payment_month():
    assert d.data_snapshot(["Jan-2019", "Mar-2019", None, ""]) == SNAPSHOT


@pytest.mark.parametrize(
    "issue, term, expected",
    [
        ("Mar-2016", " 36 months", True),   # ends Mar 2019, exactly at the snapshot
        ("Apr-2016", " 36 months", False),  # ends Apr 2019, after the snapshot
        ("Mar-2014", " 60 months", True),
        ("Apr-2014", " 60 months", False),
        ("", " 36 months", False),          # blank issue date
        ("Mar-2016", "", False),            # blank term
    ],
)
def test_has_matured(issue, term, expected):
    assert bool(d.has_matured([issue], [term], SNAPSHOT).iloc[0]) == expected


def test_modelling_population_keeps_only_finished_matured_individual_loans():
    raw = pd.DataFrame(
        [
            loan(),                                            # kept, fully paid
            loan(loan_status="Charged Off"),                   # kept, default
            loan(loan_status="Current"),                       # no outcome yet
            loan(application_type="Joint App"),                # joint application
            loan(issue_d="Jan-2018", loan_status="Charged Off"),  # not matured
        ]
    )

    population = d.modelling_population(raw, SNAPSHOT)

    assert list(population.index) == [0, 1]
    assert list(population["default"]) == [0, 1]


# --- Features -----------------------------------------------------------------------


def test_build_features_valid_row():
    features = d.build_features(pd.DataFrame([loan()])).iloc[0]

    assert list(d.build_features(pd.DataFrame([loan()])).columns) == list(d.FULL_FEATURES)
    assert features["term_months"] == 36
    assert features["purpose_group"] == "personal"
    assert features["loan_to_income"] == pytest.approx(12000 / 60000)
    assert features["repayment_burden"] == pytest.approx((12000 / 36) / (60000 / 12))
    assert features["emp_length_years"] == 5
    assert features["fico_score"] == 692
    assert features["credit_history_months"] == 120


def test_build_features_blank_and_invalid_values_become_missing():
    row = loan(annual_inc=0.0, dti=-1.0, emp_length=None, term="", purpose="", earliest_cr_line="")

    features = d.build_features(pd.DataFrame([row])).iloc[0]

    for name in ["loan_to_income", "repayment_burden", "dti", "emp_length_years", "term_months",
                 "credit_history_months"]:
        assert pd.isna(features[name]), name
    assert pd.isna(features["purpose_group"])


# --- Feature sets and column catalogue ----------------------------------------------


def test_every_raw_column_has_exactly_one_role():
    assert sorted(d.COLUMN_ROLES) == sorted(RAW_HEADER)
    assert len(RAW_HEADER) == 151
    assert set(d.COLUMN_ROLES.values()) <= set(d.ROLE_REASONS)


def test_features_are_only_built_from_allowed_columns():
    for feature in d.FEATURES:
        for source in feature.sources:
            assert d.COLUMN_ROLES[source] in {"feature", "population_filter"}, (feature.name, source)


@pytest.mark.parametrize("role", ["after_scoring", "lender_risk_output", "protected_or_proxy", "target"])
def test_no_feature_uses_leaky_lender_or_protected_columns(role):
    banned = {col for col, r in d.COLUMN_ROLES.items() if r == role}
    used = {source for f in d.FEATURES for source in f.sources}

    assert not used & banned


def test_feature_sets_cover_all_four_ir_groups():
    assert {f.group for f in d.FEATURES} == set(d.GROUPS)
    assert {f.group for f in d.FEATURES if f.feature_set == "deployable"} >= {"application", "repayment capacity", "stability"}


def test_deployable_set_is_inside_full_set_and_has_no_bureau_fields():
    assert set(d.DEPLOYABLE_FEATURES) < set(d.FULL_FEATURES)
    assert all(f.available == "yes" for f in d.FEATURES if f.feature_set == "deployable")
    assert all(f.group == "credit history" for f in d.FEATURES if f.feature_set == "full")


def test_no_feature_is_an_interest_rate():
    """Shariah mode must never store or show an interest rate, so no model input can be one."""
    used = {source for f in d.FEATURES for source in f.sources}

    assert not used & {"int_rate", "installment", "total_rec_int"}


def test_catalogue_and_feature_tables_have_one_row_each():
    assert len(d.column_catalogue()) == 151
    assert list(d.feature_table()["feature"]) == list(d.FULL_FEATURES)
