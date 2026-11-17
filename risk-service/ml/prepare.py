"""Convert the downloaded LendingClub CSV into a Parquet file, once (PB-12).

The raw file is 1.6 GB of text with 151 columns. Reading it with pandas in one go needs
more memory than an 8 GB laptop has, and takes minutes every time. This script streams
it in batches, gives every column an explicit type, drops LendingClub's footer rows and
writes `data/raw/accepted_2007_to_2018Q4.parquet`. The notebook and the training
pipeline then read only the columns they need from the Parquet file, in seconds.

Run from the risk-service folder:

    python -m ml.prepare
"""

import argparse
from pathlib import Path

import pyarrow as pa
import pyarrow.csv as pv
import pyarrow.parquet as pq

from ml.dataset import RAW_TEXT_COLUMNS

RAW_DIR = Path(__file__).resolve().parent.parent / "data" / "raw"
DEFAULT_SOURCE = RAW_DIR / "accepted_2007_to_2018Q4.csv.gz"
DEFAULT_TARGET = RAW_DIR / "accepted_2007_to_2018Q4.parquet"


def column_types(header):
    """Map each CSV column to an Arrow type: text columns stay strings, the rest are numbers.

    Naming every type up front stops Arrow from guessing a type from the first batch and
    then failing on a later batch that disagrees (for example a column that is empty for
    the first 100,000 rows).
    """
    return {name: pa.string() if name in RAW_TEXT_COLUMNS else pa.float64() for name in header}


def is_loan_row(batch):
    """True for real loan rows. LendingClub's footer rows ("Total amount funded in
    policy code 1: ...") have text in `id` and every other column empty."""
    return batch.column("loan_amnt").is_valid()


def convert(source, target, block_size=64 << 20):
    """Stream `source` (CSV or CSV.gz) into `target` (Parquet). Returns the row count."""
    source, target = Path(source), Path(target)
    reader = pv.open_csv(
        source,
        read_options=pv.ReadOptions(block_size=block_size),
        # strings_can_be_null: an empty text field becomes a missing value, not "", so
        # missing-value counts are right for text columns as well as numbers.
        convert_options=pv.ConvertOptions(column_types=_types_for(source), strings_can_be_null=True),
    )
    rows = 0
    tmp = target.with_suffix(".parquet.tmp")
    with pq.ParquetWriter(tmp, reader.schema, compression="zstd") as writer:
        for batch in reader:
            batch = batch.filter(is_loan_row(batch))
            writer.write_batch(batch)
            rows += batch.num_rows
    tmp.replace(target)  # only replace an existing file once the new one is complete
    return rows


def _types_for(source):
    with pv.open_csv(source, read_options=pv.ReadOptions(block_size=1 << 20)) as probe:
        return column_types(probe.schema.names)


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--source", default=DEFAULT_SOURCE, help="CSV or CSV.gz from Kaggle")
    parser.add_argument("--target", default=DEFAULT_TARGET, help="Parquet file to write")
    args = parser.parse_args()
    rows = convert(args.source, args.target)
    print(f"Wrote {rows:,} loans to {args.target}")


if __name__ == "__main__":
    main()
