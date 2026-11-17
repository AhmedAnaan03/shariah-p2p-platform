"""Tests for the CSV -> Parquet conversion in ml/prepare.py (PB-12)."""

import gzip

import pandas as pd

from ml import prepare

CSV = (
    "id,loan_amnt,term,emp_length,annual_inc\n"
    "1,1000.0, 36 months,5 years,50000\n"
    "2,2000.0, 60 months,,\n"
    "Total amount funded in policy code 1: 3000,,,,\n"
)


def test_convert_drops_footer_rows_and_keeps_types(tmp_path):
    source = tmp_path / "loans.csv.gz"
    with gzip.open(source, "wt") as f:
        f.write(CSV)
    target = tmp_path / "loans.parquet"

    rows = prepare.convert(source, target)

    df = pd.read_parquet(target)
    assert rows == 2
    assert list(df["id"]) == ["1", "2"]  # footer row gone, id kept as text
    assert df["loan_amnt"].dtype == "float64"
    assert df["term"].tolist() == [" 36 months", " 60 months"]


def test_convert_reads_blank_text_and_numbers_as_missing(tmp_path):
    source = tmp_path / "loans.csv"
    source.write_text(CSV)
    target = tmp_path / "loans.parquet"

    prepare.convert(source, target)

    df = pd.read_parquet(target)
    assert pd.isna(df.loc[1, "emp_length"])  # blank text -> missing, not ""
    assert pd.isna(df.loc[1, "annual_inc"])


def test_column_types_text_columns_are_strings_rest_numbers():
    types = prepare.column_types(["id", "loan_amnt", "purpose"])

    assert str(types["id"]) == "string"
    assert str(types["purpose"]) == "string"
    assert str(types["loan_amnt"]) == "double"
