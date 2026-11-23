"""LendingClub dataset rules (PB-12).

One place for every decision about the training data:

- which loans are in the modelling population and what counts as a default (the label),
- how raw columns become model features, grouped by the IR's four groups,
- the three feature sets PB-21 trains and compares: "deployable" (fields our PB-13/PB-14
  form collects), "self_declared" (adds three credit-history answers an admin can check
  against the borrower's MMA credit report) and "full" (adds bureau-only fields such as
  FICO; a benchmark, since Maldivian borrowers cannot supply them),
- why every one of the other raw columns is left out.

The exploration notebook (notebooks/PB-12-eda.ipynb) and the training pipeline (PB-21)
both import from here, so the rules cannot drift apart.
"""

from dataclasses import dataclass

import pandas as pd

# ---------------------------------------------------------------------------------------
# Raw file
# ---------------------------------------------------------------------------------------

# Columns that hold text in the raw CSV. Every other column is numeric.
RAW_TEXT_COLUMNS = frozenset(
    {
        "id", "member_id", "term", "grade", "sub_grade", "emp_title", "emp_length",
        "home_ownership", "verification_status", "issue_d", "loan_status", "pymnt_plan",
        "url", "desc", "purpose", "title", "zip_code", "addr_state", "earliest_cr_line",
        "initial_list_status", "last_pymnt_d", "next_pymnt_d", "last_credit_pull_d",
        "application_type", "verification_status_joint", "sec_app_earliest_cr_line",
        "hardship_flag", "hardship_type", "hardship_reason", "hardship_status",
        "hardship_start_date", "hardship_end_date", "payment_plan_start_date",
        "hardship_loan_status", "disbursement_method", "debt_settlement_flag",
        "debt_settlement_flag_date", "settlement_status", "settlement_date",
    }
)

# ---------------------------------------------------------------------------------------
# Label
# ---------------------------------------------------------------------------------------

# Final loan outcomes. "Default" is LendingClub's status for 121+ days late and not yet
# written off, so it counts as a default. "Does not meet the credit policy" loans are
# 2007-2010 loans that a later LendingClub policy would have refused; their outcome is
# still real, so they keep their label.
# Anything else (Current, In Grace Period, Late) has no final outcome yet: no label.
OUTCOME_LABELS = {
    "Fully Paid": 0,
    "Does not meet the credit policy. Status:Fully Paid": 0,
    "Charged Off": 1,
    "Default": 1,
    "Does not meet the credit policy. Status:Charged Off": 1,
}


def default_label(status):
    """1 = defaulted, 0 = fully paid, <NA> = no final outcome (or not a known status)."""
    return pd.Series(status).map(OUTCOME_LABELS).astype("Int8")


# ---------------------------------------------------------------------------------------
# Parsing helpers (each returns NaN/NaT for blank or invalid input instead of failing)
# ---------------------------------------------------------------------------------------

EMP_LENGTH_YEARS = {"< 1 year": 0, "1 year": 1, **{f"{n} years": n for n in range(2, 10)}, "10+ years": 10}


def parse_month(values):
    """'Aug-2003' -> 2003-08-01. Blank or invalid -> NaT."""
    return pd.to_datetime(pd.Series(values, dtype="string"), format="%b-%Y", errors="coerce")


def term_months(values):
    """' 36 months' -> 36. Blank or invalid -> NaN."""
    digits = pd.Series(values, dtype="string").str.extract(r"^\s*(\d+)\s*months?\s*$", expand=False)
    return pd.to_numeric(digits, errors="coerce").astype("float64")


def emp_length_years(values):
    """'10+ years' -> 10, '< 1 year' -> 0. Blank, 'n/a' or invalid -> NaN.

    Missing is kept as missing: borrowers who left it blank default more often (see the
    notebook), so the model should see the gap rather than a filled-in guess."""
    return pd.Series(values, dtype="string").map(EMP_LENGTH_YEARS).astype("float64")


def months_between(start, end):
    """Whole calendar months from `start` to `end`. Missing, or `end` before `start` -> NaN."""
    start, end = pd.Series(start), pd.Series(end)
    months = (end.dt.year - start.dt.year) * 12 + (end.dt.month - start.dt.month)
    return months.where(months >= 0).astype("float64")


def safe_ratio(numerator, denominator):
    """numerator / denominator, with NaN where the denominator is zero, negative or missing."""
    numerator, denominator = pd.Series(numerator, dtype="float64"), pd.Series(denominator, dtype="float64")
    return (numerator / denominator.where(denominator > 0)).astype("float64")


# ---------------------------------------------------------------------------------------
# Category mappings: LendingClub values and our own form's values -> one shared vocabulary
# ---------------------------------------------------------------------------------------

# PB-13's purpose options are start-up, growth, education and personal (stored by the web
# app as start_up, growth, education, personal). LendingClub has one business purpose, so
# start-up and growth both map to "business".
LENDINGCLUB_PURPOSES = {
    "small_business": "business",
    "educational": "education",
    **{
        p: "personal"
        for p in [
            "debt_consolidation", "credit_card", "home_improvement", "other", "major_purchase",
            "medical", "car", "moving", "vacation", "house", "wedding", "renewable_energy",
        ]
    },
}
PLATFORM_PURPOSES = {"start_up": "business", "growth": "business", "education": "education", "personal": "personal"}


def purpose_group(values, mapping=LENDINGCLUB_PURPOSES):
    """LendingClub purpose (or, with mapping=PLATFORM_PURPOSES, our form's purpose) ->
    business / education / personal. Unknown or blank -> <NA>."""
    return pd.Series(values, dtype="string").map(mapping).astype("string")


HOME_OWNERSHIP_GROUPS = {"RENT": "RENT", "MORTGAGE": "MORTGAGE", "OWN": "OWN", "OTHER": "OTHER", "ANY": "OTHER", "NONE": "OTHER"}


def home_ownership_group(values):
    """RENT / MORTGAGE / OWN stay; LendingClub's rare ANY, NONE and OTHER (0.04% of loans)
    become OTHER. Blank or unknown -> <NA>."""
    return pd.Series(values, dtype="string").str.upper().map(HOME_OWNERSHIP_GROUPS).astype("string")


# ---------------------------------------------------------------------------------------
# Modelling population
# ---------------------------------------------------------------------------------------


def data_snapshot(last_payment_dates):
    """The month the data was extracted: the latest payment date anywhere in the file."""
    return parse_month(last_payment_dates).max()


def has_matured(issue_dates, terms, snapshot):
    """True when the loan's full term had ended by `snapshot`.

    A loan issued in 2017 on a 60-month term could not have been fully paid by the 2019
    snapshot; the only 2017 loans that are already finished are early defaults and early
    payoffs. Keeping only matured loans stops recent years from looking riskier (or
    safer) than they were. Missing dates or terms -> False."""
    issue = parse_month(issue_dates)
    months = term_months(terms)
    snapshot = pd.Timestamp(snapshot)
    issue_index = issue.dt.year * 12 + issue.dt.month
    return ((issue_index + months) <= (snapshot.year * 12 + snapshot.month)).fillna(False).astype(bool)


def modelling_population(df, snapshot):
    """Rows the model learns from, with a `default` column (0/1).

    Kept: individual applications (our platform has one borrower per request), from
    cohorts whose term had ended by `snapshot`, with a final outcome."""
    label = default_label(df["loan_status"].to_numpy())
    label.index = df.index
    keep = (
        (df["application_type"] == "Individual")
        & has_matured(df["issue_d"].to_numpy(), df["term"].to_numpy(), snapshot).set_axis(df.index)
        & label.notna()
    )
    out = df.loc[keep].copy()
    out["default"] = label[keep].astype("int8")
    return out


# ---------------------------------------------------------------------------------------
# Features
# ---------------------------------------------------------------------------------------

GROUPS = ("application", "repayment capacity", "credit history", "stability")


@dataclass(frozen=True)
class Feature:
    name: str
    group: str  # one of GROUPS
    tier: str  # "deployable", "self_declared" or "bureau" (see FEATURE_SETS)
    sources: tuple  # raw columns it is built from
    meaning: str
    maldives: str  # can a Maldivian borrower realistically provide it on our form?
    available: str  # short form of `maldives`, for the feature table


SELF_DECLARED = "self-declared, verifiable via CIS report"

FEATURES = (
    # --- Deployable: our PB-13 and PB-14 form can collect every one of these ----------
    Feature(
        "term_months", "application", "deployable", ("term",),
        "Repayment period in months (36 or 60 in LendingClub).",
        "Yes: tenure on the PB-13 request. LendingClub only has 36 and 60 months, so a "
        "shorter tenure is outside what the model has seen.",
        "yes",
    ),
    Feature(
        "purpose_group", "application", "deployable", ("purpose",),
        "business / education / personal.",
        "Yes: PB-13 purpose (start-up and growth map to business). Education has very few "
        "training loans, so the model can say little about it.",
        "yes",
    ),
    Feature(
        "loan_to_income", "repayment capacity", "deployable", ("loan_amnt", "annual_inc"),
        "Amount requested divided by annual income.",
        "Yes: amount (PB-13) / declared annual income (PB-14). A ratio, so it carries "
        "over from US dollars to MVR, unlike the raw amounts.",
        "yes",
    ),
    Feature(
        "repayment_burden", "repayment capacity", "deployable", ("loan_amnt", "term", "annual_inc"),
        "Monthly principal (amount / months) divided by monthly income. Leaves out "
        "interest or profit, so it means the same in conventional and Shariah mode.",
        "Yes: worked out from the PB-13 amount and tenure and the PB-14 income.",
        "yes",
    ),
    Feature(
        "dti", "repayment capacity", "deployable", ("dti",),
        "Existing monthly debt repayments (not housing, not this loan) as a % of monthly income.",
        "Yes: PB-14 asks for monthly repayments on existing loans and cards, backed by a "
        "bank statement; the platform works out the percentage.",
        "yes",
    ),
    Feature(
        "emp_length_years", "stability", "deployable", ("emp_length",),
        "Years in current job, 0-10 (10 means 10 or more). Missing kept as missing.",
        "Yes: years in current job or running the business (PB-14), backed by an "
        "employment letter or business registration.",
        "yes",
    ),
    Feature(
        "home_ownership", "stability", "deployable", ("home_ownership",),
        "RENT / MORTGAGE / OWN / OTHER.",
        "Yes, but living in a family-owned home is common in the Maldives and has no "
        "clear LendingClub match. PB-14 should offer it and map it to OTHER, which has "
        "few training loans, so it scores near average.",
        "yes",
    ),
    # --- Self-declared history: the borrower answers on the PB-14 form; an admin checks the
    # answers against the MMA Credit Information System report they upload ------------
    Feature(
        "open_acc", "credit history", "self_declared", ("open_acc",),
        "Open credit lines.",
        "Self-declared: number of open loans, financing facilities and credit cards. An "
        "admin checks it against the active facilities on the borrower's uploaded MMA "
        "Credit Information System report.",
        SELF_DECLARED,
    ),
    Feature(
        "delinq_2yrs", "credit history", "self_declared", ("delinq_2yrs",),
        "Payments 30+ days late in the past 2 years.",
        "Self-declared: times a repayment was 30 or more days late in the past 2 years, "
        "checked against the repayment history on the uploaded MMA report.",
        SELF_DECLARED,
    ),
    Feature(
        "pub_rec", "credit history", "self_declared", ("pub_rec",),
        "Derogatory public records (bankruptcies, judgments, tax liens).",
        "Self-declared: court judgments, bankruptcy or debts written off. The closest "
        "local equivalent of LendingClub's US public records, so the meaning is "
        "approximate; written-off facilities are checked on the uploaded MMA report.",
        SELF_DECLARED,
    ),
    # --- Bureau only: needs a credit bureau the Maldives does not have in this form
    # (full benchmark set only) ----------------------------------------------------------
    Feature(
        "fico_score", "credit history", "bureau", ("fico_range_low", "fico_range_high"),
        "Middle of the FICO range at application.",
        "No: there is no FICO-style score in the Maldives. The MMA Credit Information "
        "System self-inquiry report lists facilities and repayments, not a score.",
        "no",
    ),
    Feature(
        "credit_history_months", "credit history", "bureau", ("earliest_cr_line", "issue_d"),
        "Months since the first credit line was opened. Partly tracks age; check it in "
        "the PB-23 SHAP review.",
        "Partial: date of the first facility on a CIS self-inquiry report, typed in by "
        "an admin. Only covers banks and finance companies.",
        "partial",
    ),
    Feature(
        "inq_last_6mths", "credit history", "bureau", ("inq_last_6mths",),
        "Credit enquiries by lenders in the past 6 months.",
        "Partial at best: only if the CIS report lists lender enquiries (not confirmed).",
        "partial",
    ),
    Feature(
        "total_acc", "credit history", "bureau", ("total_acc",),
        "Credit lines ever opened.",
        "Partial: all facilities on a CIS self-inquiry report.",
        "partial",
    ),
    Feature(
        "revol_util", "credit history", "bureau", ("revol_util",),
        "Credit-card balances as a % of card limits.",
        "Partial: needs card balances and limits, if the CIS report shows them.",
        "partial",
    ),
)

# The three sets PB-21 trains. Each one adds a tier to the one before it.
#   deployable     what our PB-13/PB-14 form collects as plain facts
#   self_declared  deployable + credit-history answers the borrower gives on the form and
#                  an admin checks against their uploaded MMA credit report
#   full           self_declared + bureau-only fields (FICO and the like); a benchmark only,
#                  because a Maldivian borrower cannot supply these
FEATURE_SETS = {
    "deployable": tuple(f.name for f in FEATURES if f.tier == "deployable"),
    "self_declared": tuple(f.name for f in FEATURES if f.tier in {"deployable", "self_declared"}),
    "full": tuple(f.name for f in FEATURES),
}
DEPLOYABLE_FEATURES = FEATURE_SETS["deployable"]
SELF_DECLARED_FEATURES = FEATURE_SETS["self_declared"]
FULL_FEATURES = FEATURE_SETS["full"]
CATEGORICAL_FEATURES = ("purpose_group", "home_ownership")


def build_features(df, purposes=LENDINGCLUB_PURPOSES):
    """Turn raw LendingClub columns into every candidate feature (the full set).

    Select one of `FEATURE_SETS` from the result to train on it. `purposes` maps the
    `purpose` column; platform_features() passes PLATFORM_PURPOSES."""
    issue = parse_month(df["issue_d"].to_numpy())
    term = term_months(df["term"].to_numpy())
    annual_income = pd.Series(df["annual_inc"].to_numpy(), dtype="float64")
    amount = pd.Series(df["loan_amnt"].to_numpy(), dtype="float64")
    dti = pd.Series(df["dti"].to_numpy(), dtype="float64")

    out = pd.DataFrame(
        {
            "term_months": term,
            "purpose_group": purpose_group(df["purpose"].to_numpy(), mapping=purposes),
            "loan_to_income": safe_ratio(amount, annual_income),
            "repayment_burden": safe_ratio(safe_ratio(amount, term), annual_income / 12),
            "dti": dti.where(dti >= 0),  # one loan has dti = -1: not a valid ratio
            "emp_length_years": emp_length_years(df["emp_length"].to_numpy()),
            "home_ownership": home_ownership_group(df["home_ownership"].to_numpy()),
            "fico_score": (pd.Series(df["fico_range_low"].to_numpy(), dtype="float64")
                           + pd.Series(df["fico_range_high"].to_numpy(), dtype="float64")) / 2,
            "credit_history_months": months_between(parse_month(df["earliest_cr_line"].to_numpy()), issue),
        }
    )
    for name in ["delinq_2yrs", "inq_last_6mths", "open_acc", "total_acc", "revol_util", "pub_rec"]:
        out[name] = pd.Series(df[name].to_numpy(), dtype="float64")
    out.index = df.index
    return out[list(FULL_FEATURES)]


# ---------------------------------------------------------------------------------------
# Our platform's applications (PB-13 / PB-14) -> the same features
# ---------------------------------------------------------------------------------------

# The web app's housing options -> LendingClub's home_ownership. A family home has no
# LendingClub match and becomes OTHER (see the home_ownership feature above).
PLATFORM_HOUSING = {"rent": "RENT", "own": "OWN", "own_with_home_loan": "MORTGAGE", "family_home": "OTHER"}

# The web app stores years in the job as 0-10 (0 = less than a year, 10 = ten or more).
PLATFORM_EMP_LENGTH = {years: text for text, years in EMP_LENGTH_YEARS.items()}

# LendingClub terms are only 36 or 60 months. Our form also offers 3-48 months, which are
# outside the training range; PB-21 must treat term_months as a number, not a category,
# so the model can still place them.
TRAINED_TERMS = (36, 60)

# The columns build_features() reads for the bureau-only features. Our platform has no
# values for them, so they are left missing.
_BUREAU_ONLY_COLUMNS = (
    "fico_range_low", "fico_range_high", "earliest_cr_line", "issue_d",
    "inq_last_6mths", "total_acc", "revol_util",
)


def platform_raw_frame(applications):
    """Our applications (dicts with the web app's field names) as raw LendingClub columns.

    LendingClub's `dti` is monthly debt repayments (excluding housing) / monthly income x 100;
    our financial profile asks for the repayments and the monthly income (PB-58), so annual
    income (monthly x 12, as the model was trained on) and dti are worked out here."""
    apps = pd.DataFrame(list(applications))
    annual_income = pd.Series(apps["monthly_income"], dtype="float64") * 12
    monthly_debt = pd.Series(apps["monthly_debt_repayments"], dtype="float64")
    raw = pd.DataFrame(
        {
            "loan_amnt": pd.Series(apps["amount_requested"], dtype="float64"),
            "term": apps["tenure_months"].map(lambda n: f"{int(n)} months"),
            "purpose": apps["purpose"],
            "annual_inc": annual_income,
            "dti": safe_ratio(monthly_debt, annual_income / 12) * 100,
            "emp_length": apps["employment_years"].map(PLATFORM_EMP_LENGTH),
            "home_ownership": apps["housing"].map(PLATFORM_HOUSING),
            "open_acc": pd.Series(apps["open_credit_lines"], dtype="float64"),
            "delinq_2yrs": pd.Series(apps["late_payments_2y"], dtype="float64"),
            "pub_rec": pd.Series(apps["public_records"], dtype="float64"),
        }
    )
    for column in _BUREAU_ONLY_COLUMNS:
        raw[column] = None
    return raw


def platform_features(applications, feature_set="self_declared"):
    """Model features for our applications, built by the same build_features() code as the
    training data. The web app's RiskFeatures (PHP) must agree with this; the shared fixture
    tests/fixtures/feature_parity.json is checked by both test suites."""
    features = build_features(platform_raw_frame(applications), purposes=PLATFORM_PURPOSES)
    return features[list(FEATURE_SETS[feature_set])]


# ---------------------------------------------------------------------------------------
# Every raw column, and why it is (or is not) used
# ---------------------------------------------------------------------------------------

ROLE_REASONS = {
    "feature": "Used to build a feature (see FEATURES).",
    "target": "The loan outcome; becomes the label.",
    "population_filter": "Only used to choose the modelling population or to work out a feature date.",
    "identifier": "Identifies the loan or borrower; no meaning for risk.",
    "after_scoring": (
        "Only known after our platform would score the request: funding, repayments, "
        "collections, hardship plans or settlements. Using it would leak the outcome."
    ),
    "lender_risk_output": (
        "LendingClub's own grade or price, produced by its model from the same inputs. "
        "Learning from it would copy their model, and an interest rate cannot exist in "
        "Shariah mode."
    ),
    "lender_operational": "A LendingClub process setting, not something about the borrower.",
    "protected_or_proxy": (
        "Location or free text that can stand in for ethnicity, religion, health or "
        "identity. Left out for fairness."
    ),
    "selection_effect": (
        "LendingClub chose whom to verify (riskier-looking applicants), so verified loans "
        "default more. On our platform every borrower is verified, so it would be constant."
    ),
    "joint_application": "Second-applicant fields; the population is individual applications only.",
    "late_bureau_field": (
        "Credit-bureau detail LendingClub only began recording in 2012 or late 2015. It is "
        "missing for older loans, so its gaps track the issue date rather than the "
        "borrower; it overlaps the core bureau fields and has no Maldivian source."
    ),
    "redundant": "Repeats, or is part of, a kept feature, or is mostly missing with no signal.",
    "near_constant": "Zero for over 98% of loans in the population; too rare to learn from.",
}


def _roles(role, names):
    return {name: role for name in names.split()}


COLUMN_ROLES = {
    **_roles("feature", """
        loan_amnt term purpose annual_inc dti emp_length home_ownership fico_range_low
        fico_range_high earliest_cr_line delinq_2yrs inq_last_6mths open_acc total_acc
        revol_util pub_rec"""),
    **_roles("target", "loan_status"),
    **_roles("population_filter", "issue_d application_type"),
    **_roles("identifier", "id member_id url"),
    **_roles("after_scoring", """
        funded_amnt funded_amnt_inv pymnt_plan out_prncp out_prncp_inv total_pymnt
        total_pymnt_inv total_rec_prncp total_rec_int total_rec_late_fee recoveries
        collection_recovery_fee last_pymnt_d last_pymnt_amnt next_pymnt_d last_credit_pull_d
        last_fico_range_high last_fico_range_low hardship_flag hardship_type hardship_reason
        hardship_status deferral_term hardship_amount hardship_start_date hardship_end_date
        payment_plan_start_date hardship_length hardship_dpd hardship_loan_status
        orig_projected_additional_accrued_interest hardship_payoff_balance_amount
        hardship_last_payment_amount debt_settlement_flag debt_settlement_flag_date
        settlement_status settlement_date settlement_amount settlement_percentage settlement_term"""),
    **_roles("lender_risk_output", "grade sub_grade int_rate installment"),
    **_roles("lender_operational", "initial_list_status policy_code disbursement_method"),
    **_roles("protected_or_proxy", "zip_code addr_state emp_title desc title"),
    **_roles("selection_effect", "verification_status"),
    **_roles("joint_application", """
        annual_inc_joint dti_joint verification_status_joint revol_bal_joint
        sec_app_fico_range_low sec_app_fico_range_high sec_app_earliest_cr_line
        sec_app_inq_last_6mths sec_app_mort_acc sec_app_open_acc sec_app_revol_util
        sec_app_open_act_il sec_app_num_rev_accts sec_app_chargeoff_within_12_mths
        sec_app_collections_12_mths_ex_med sec_app_mths_since_last_major_derog"""),
    **_roles("late_bureau_field", """
        mort_acc mths_since_last_major_derog tot_coll_amt tot_cur_bal open_acc_6m open_act_il
        open_il_12m open_il_24m mths_since_rcnt_il total_bal_il il_util open_rv_12m
        open_rv_24m max_bal_bc all_util total_rev_hi_lim inq_fi total_cu_tl inq_last_12m
        acc_open_past_24mths avg_cur_bal bc_open_to_buy bc_util mo_sin_old_il_acct
        mo_sin_old_rev_tl_op mo_sin_rcnt_rev_tl_op mo_sin_rcnt_tl mths_since_recent_bc
        mths_since_recent_bc_dlq mths_since_recent_inq mths_since_recent_revol_delinq
        num_accts_ever_120_pd num_actv_bc_tl num_actv_rev_tl num_bc_sats num_bc_tl num_il_tl
        num_op_rev_tl num_rev_accts num_rev_tl_bal_gt_0 num_sats num_tl_120dpd_2m
        num_tl_30dpd num_tl_90g_dpd_24m num_tl_op_past_12m pct_tl_nvr_dlq percent_bc_gt_75
        tot_hi_cred_lim total_bal_ex_mort total_bc_limit total_il_high_credit_limit"""),
    **_roles("redundant", "revol_bal pub_rec_bankruptcies tax_liens mths_since_last_delinq mths_since_last_record"),
    **_roles("near_constant", "acc_now_delinq collections_12_mths_ex_med chargeoff_within_12_mths delinq_amnt"),
}

# Specific notes for columns where the general reason needs a word more.
COLUMN_NOTES = {
    "funded_amnt": "Set when investors fund the loan, after our scoring step.",
    "funded_amnt_inv": "Set when investors fund the loan, after our scoring step.",
    "last_fico_range_high": "FICO pulled during repayment, so it already reflects missed payments.",
    "last_fico_range_low": "FICO pulled during repayment, so it already reflects missed payments.",
    "installment": "Worked out from int_rate; repayment_burden is the interest-free version.",
    "zip_code": "First 3 digits of a US ZIP code: a known proxy for race and income area.",
    "addr_state": "US state; on our platform the equivalent (atoll or island) is left out too.",
    "emp_title": "Free-text job title; can reveal identity and is not standardised.",
    "desc": "Free-text loan description written by the borrower.",
    "title": "Free-text loan title; purpose already records the category.",
    "mort_acc": "Introduced Mar 2012; home_ownership = MORTGAGE carries most of the same signal.",
    "revol_bal": "Absolute US dollar balance; revol_util is the currency-free version.",
    "pub_rec_bankruptcies": "A subset of pub_rec.",
    "tax_liens": "A subset of pub_rec.",
    "mths_since_last_delinq": "51% missing; delinq_2yrs covers recent late payments.",
    "mths_since_last_record": "84% missing; pub_rec covers it.",
    "fico_range_high": "Always fico_range_low + 4; both feed fico_score.",
    "loan_amnt": "Not used on its own: absolute US dollars do not transfer to MVR. Used in loan_to_income and repayment_burden.",
    "annual_inc": "Not used on its own, for the same reason. Used in the ratio features.",
}


def column_catalogue():
    """One row per raw column: role, reason, note. Used for the exclusion table."""
    rows = [
        {"column": col, "role": role, "reason": ROLE_REASONS[role], "note": COLUMN_NOTES.get(col, "")}
        for col, role in COLUMN_ROLES.items()
    ]
    return pd.DataFrame(rows)


def feature_table():
    """The recommended feature list as a table (for the notebook, DATA.md and the report)."""
    return pd.DataFrame(
        [
            {
                "feature": f.name,
                "IR group": f.group,
                "in sets": ", ".join(name for name, members in FEATURE_SETS.items() if f.name in members),
                "built from": ", ".join(f.sources),
                "meaning": f.meaning,
                "on our form?": f.available,
                "Maldives note": f.maldives,
            }
            for f in FEATURES
        ]
    )

