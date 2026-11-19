# Training data (PB-12)

The risk model is trained on LendingClub's public loan records. This file records where the
data comes from, how to get it, what is in it and which parts the model may use. The
reasoning, charts and numbers are in `notebooks/PB-12-eda.ipynb`; the rules themselves are
code in `ml/dataset.py`.

## Source

| | |
|---|---|
| Dataset | "All Lending Club loan data", accepted loans file `accepted_2007_to_2018Q4.csv.gz` |
| Publisher | LendingClub (original); compiled on Kaggle by user `wordsforthewise` |
| URL | https://www.kaggle.com/datasets/wordsforthewise/lending-club |
| Licence | **CC0 1.0 (public domain)**, as stated in the Kaggle dataset metadata |
| Size | 393 MB compressed (1.68 GB as CSV); 2,260,668 loans × 151 columns |
| Time period | Loans issued Jun 2007 to Dec 2018; repayment data up to Mar 2019 |
| Outcome | `loan_status` (Fully Paid, Charged Off, Default, Current, Late, ...) |
| Default rate, all finished loans | 19.98% (269,360 of 1,348,099) |
| Default rate, **modelling population** | **15.19%** (118,112 of 777,399) |
| Downloaded | 18 Nov 2026 |

The same Kaggle dataset also has a rejected-applications file, which is not used: it has no
outcome. LendingClub's field definitions (`LCDataDictionary.xlsx`) are not part of this
Kaggle dataset; the column meanings used here are summarised in `ml/dataset.py` and the
notebook.

**Why this dataset.** It is real peer-to-peer lending, the same business model as our
platform, with a real default outcome, enough loans for cross-validation and SMOTE (PB-21),
and fields for all four IR feature groups. Alternatives considered:

| Dataset | Why not |
|---|---|
| Home Credit Default Risk (Kaggle competition) | Competition-only licence; the data may not be redistributed or used outside the competition |
| German Credit (UCI) | 1,000 loans; age, sex and foreign-worker status are built into its attributes |
| Give Me Some Credit (Kaggle) | No loan amount, purpose or term, so no application group |
| Taiwan credit card default (UCI) | Credit-card bills, not instalment financing |
| US SBA 7(a) loans | Small-business loans (relevant to our SMEs) but no borrower income, debt or credit history. Noted for PB-49 |

## How to download it

You need a Kaggle account and an API token. The token never goes into the repository.

1. On kaggle.com, open **Settings → API → Create New Token**. Save the token as
   `~/.kaggle/access_token` (newer tokens), or save the downloaded `kaggle.json` as
   `~/.kaggle/kaggle.json` (older tokens). Run `chmod 600` on the file.
2. From `risk-service/`:

   ```sh
   source .venv/bin/activate
   pip install -r requirements-notebook.txt           # kaggle CLI, Jupyter, matplotlib
   kaggle datasets download wordsforthewise/lending-club \
       -f accepted_2007_to_2018Q4.csv.gz -p data/raw    # 393 MB, about 30 seconds
   python -m ml.prepare                               # CSV -> Parquet, about 30 seconds
   ```

   `ml.prepare` writes `data/raw/accepted_2007_to_2018Q4.parquet` (302 MB). It streams the
   CSV in batches with a fixed type for every column, so it peaks at about 2 GB of memory,
   and it drops LendingClub's 33 "Total amount funded..." footer rows.

**Without the CLI:** sign in at the URL above, click **Download**, and put
`accepted_2007_to_2018Q4.csv.gz` (unzipped from the archive Kaggle gives you, if needed) in
`risk-service/data/raw/`. Then run `python -m ml.prepare`.

`data/raw/` is in `.gitignore`. **Never commit the raw data.**

## Opening the notebook

```sh
cd risk-service && source .venv/bin/activate
jupyter lab notebooks/PB-12-eda.ipynb
```

It runs top to bottom in under a minute and rewrites the charts in
`docs/report-notes/PB-12-*.png`. To re-run it without opening Jupyter:
`cd notebooks && jupyter nbconvert --to notebook --execute --inplace PB-12-eda.ipynb`.

## Label and modelling population

- **Default (1):** Charged Off, Default (121+ days late), or the "Does not meet the credit
  policy. Status:Charged Off" variant. **Good (0):** Fully Paid, or its "does not meet the
  credit policy" variant. Loans still running (Current, In Grace Period, Late) have no label.
- **Population:** individual applications (our platform has one borrower per request) whose
  full term had ended by the Mar 2019 snapshot. That means 36-month loans issued up to
  Mar 2016 and 60-month loans up to Mar 2014. Recent loans are left out because only their
  early defaults and early payoffs had finished, which skews their default rate (notebook,
  section 2).
- Result: **777,399 loans, 15.19% default, imbalance 5.6 : 1.**

## Feature sets

Three nested sets (`FEATURE_SETS` in `ml/dataset.py`), mapped to the IR's four groups.
**PB-21 trains all three and picks the production set from the results.** (Decision recorded
in `docs/decisions.md`.)

| Set | Features | IR groups | Role |
|---|---|---|---|
| **deployable** | 7 | application, repayment capacity, stability | Production candidate: fields our PB-13/PB-14 form collects as plain facts |
| **self_declared** (deployable + self-declared history) | 10 | all four | Production candidate: adds `open_acc`, `delinq_2yrs`, `pub_rec`, answered by the borrower and checked by an admin against their uploaded MMA Credit Information System report |
| **full** | 15 | all four | Benchmark: adds bureau-only fields such as FICO that a Maldivian borrower cannot supply |

| Feature | IR group | In sets | Built from | On our form? |
|---|---|---|---|---|
| `term_months` | application | all three | `term` | Yes (PB-13 tenure) |
| `purpose_group` | application | all three | `purpose` | Yes (PB-13; start-up and growth → business) |
| `loan_to_income` | repayment capacity | all three | `loan_amnt`, `annual_inc` | Yes (amount ÷ declared income) |
| `repayment_burden` | repayment capacity | all three | `loan_amnt`, `term`, `annual_inc` | Yes (worked out; no interest or profit in it) |
| `dti` | repayment capacity | all three | `dti` | Yes (existing monthly repayments ÷ monthly income) |
| `emp_length_years` | stability | all three | `emp_length` | Yes (years in job or business) |
| `home_ownership` | stability | all three | `home_ownership` | Yes; "family home" maps to OTHER |
| `open_acc` | credit history | self_declared, full | `open_acc` | **Self-declared, verifiable via CIS report** (open loans, facilities and cards) |
| `delinq_2yrs` | credit history | self_declared, full | `delinq_2yrs` | **Self-declared, verifiable via CIS report** (repayments 30+ days late, past 2 years) |
| `pub_rec` | credit history | self_declared, full | `pub_rec` | **Self-declared, verifiable via CIS report** (judgments, bankruptcy, written-off debts; approximate match to US public records) |
| `fico_score` | credit history | full | `fico_range_low/high` | No: no Maldivian equivalent |
| `credit_history_months` | credit history | full | `earliest_cr_line`, `issue_d` | Partial: MMA CIS self-inquiry report |
| `inq_last_6mths` | credit history | full | `inq_last_6mths` | Partial at best (enquiries not confirmed on CIS reports) |
| `total_acc` | credit history | full | `total_acc` | Partial: CIS report |
| `revol_util` | credit history | full | `revol_util` | Partial: needs card limits |

Raw `loan_amnt` and `annual_inc` are used only inside ratios, because absolute US-dollar
amounts do not carry over to MVR or Maldivian incomes.

**Self-declared answers are not bureau records.** In the training data these three values
came from a credit bureau; on our platform they come from the borrower, so their accuracy
depends on the admin's check against the uploaded report (PB-52 plans to pre-fill them from
the report). On their own they are weak (single-feature AUC 0.507 to 0.513); PB-21 measures
what they add together.

## Our applications, built the same way (PB-14)

`ml.dataset.platform_features()` turns an application from the web app (its stored field
names: `amount_requested`, `tenure_months`, `purpose`, `annual_income`,
`monthly_debt_repayments`, `employment_years`, `housing`, `open_credit_lines`,
`late_payments_2y`, `public_records`) into LendingClub's raw columns and then runs the same
`build_features()` as training. Two conversions happen on the way:

- `dti` = monthly debt repayments (excluding housing) / (annual income / 12) × 100, which is
  how LendingClub defines it.
- `housing`: rent → RENT, own → OWN, own with a home loan → MORTGAGE, family home → OTHER;
  `employment_years` 0–10 → "< 1 year" … "10+ years".

The web app's `RiskFeatures` (PHP) must give the same values. Both test suites read one fixture,
`tests/fixtures/feature_parity.json`, with hand-worked expected values for four applicants, so
a change to either side's definitions fails a test (`tests/test_feature_parity.py` here,
`web/tests/Unit/RiskFeaturesTest.php` there).

## Excluded columns

All 151 columns have a role in `ml/dataset.py` (`COLUMN_ROLES`); a test fails if one is
missed. Summary:

| Role | Columns | Why excluded |
|---|---|---|
| after_scoring | 40 | Leakage: only known after our platform would score the request (funded amount, payments, recoveries, hardship, settlement, FICO pulled during repayment) |
| lender_risk_output | 4 | `grade`, `sub_grade`, `int_rate`, `installment`: LendingClub's own model output; an interest rate cannot exist in Shariah mode |
| protected_or_proxy | 5 | `zip_code`, `addr_state`, `emp_title`, `desc`, `title`: location and free text can stand in for ethnicity, religion, health or identity. The data has no age, sex or race columns |
| selection_effect | 1 | `verification_status`: LendingClub verified riskier-looking applicants, so verified loans default more; our platform verifies everyone |
| late_bureau_field | 51 | Bureau detail added in 2012 or late 2015; gaps track the issue date, and there is no Maldivian source. Some carry real signal, so the full benchmark is conservative |
| joint_application | 16 | Second-applicant fields; the population is individuals only |
| redundant | 5 | Duplicates or parts of kept features |
| near_constant | 4 | Zero for over 98% of loans |
| identifier, lender_operational | 6 | No meaning for risk |
| target, population_filter | 3 | The outcome, and the issue date and application type used to choose the population |

## Limitations

- US consumer loans from 2007 to 2016, not Maldivian SME or personal financing. The model's
  absolute default probabilities will need recalibrating on local data (PB-49).
- LendingClub only offers 36- and 60-month terms, but our form offers 3, 6, 12, 18, 24, 36,
  48 and 60 months (PB-13). Every tenure other than 36 and 60 is outside the training range.
  PB-21 must treat `term_months` as a numeric feature, not a 36/60 category, so the model can
  still place them; the admin review page flags such requests (`TRAINED_TERMS` in
  `ml/dataset.py`, `RiskFeatures::TRAINED_TENURES` in the web app).
- Only 423 education loans and 9,925 business loans, so the model learns little that is
  specific to those purposes.
- The deployable set has no credit-history features; the self-declared set adds three, but
  they are the borrower's own answers. PB-49 plans how the platform's own repayment records
  can fill the gap over time.
