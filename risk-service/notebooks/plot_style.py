"""Chart style for the PB-12 figures, taken from the platform's design system.

Colours are the design-system tokens from web/resources/css/tailwind-theme.css, so the
report's charts match the app. The pairs were checked for colour-blind separation and
contrast on white. The brand teal (navy-600) is not used because it is too grey to tell
apart from the neutral grid.
"""

from pathlib import Path

import matplotlib as mpl
import matplotlib.pyplot as plt

FIGURE_DIR = Path(__file__).resolve().parents[2] / "docs" / "report-notes"

PAID = "#3472a3"  # risk-a-ink: lowest-risk grade colour
DEFAULTED = "#cb6440"  # chart-raise: "raises risk" in the risk explanation
TERM_60 = "#bd8b31"  # risk-c: ochre, second series beside PAID
EXCLUDED = "#9aa7ab"  # slate-400: de-emphasised (excluded) marks
INK = "#203135"  # slate-900
INK_SECONDARY = "#48595d"  # text-secondary
AXIS = "#5f6f73"  # chart-axis
GRID = "#e5edee"  # chart-grid
MIDPOINT = "#eef4f5"  # slate-50: neutral middle of the diverging scale


def apply():
    mpl.rcParams.update(
        {
            "figure.facecolor": "white",
            "axes.facecolor": "white",
            "savefig.facecolor": "white",
            "font.family": "sans-serif",
            "font.sans-serif": ["Helvetica Neue", "Arial", "DejaVu Sans"],
            "font.size": 10,
            "text.color": INK,
            "axes.labelcolor": INK_SECONDARY,
            "axes.titlesize": 11,
            "axes.titleweight": "bold",
            "axes.titlelocation": "left",
            "axes.titlecolor": INK,
            "axes.edgecolor": GRID,
            "axes.spines.top": False,
            "axes.spines.right": False,
            "axes.spines.left": False,
            "axes.grid": True,
            "axes.grid.axis": "y",
            "axes.axisbelow": True,
            "grid.color": GRID,
            "grid.linewidth": 0.8,
            "xtick.color": AXIS,
            "ytick.color": AXIS,
            "xtick.major.size": 0,
            "ytick.major.size": 0,
            "lines.linewidth": 2,
            "legend.frameon": False,
            "legend.loc": "upper left",
        }
    )


def frame(fig, title, takeaway):
    """Title and one-line takeaway above the chart, as every ChartFrame in the app has."""
    fig.suptitle(title, x=0.01, y=1.0, ha="left", va="bottom", fontsize=13, fontweight="bold", color=INK)
    fig.text(0.01, 0.995, takeaway, ha="left", va="top", fontsize=10, color=INK_SECONDARY)


def percent_axis(axis, decimals=0):
    axis.set_major_formatter(mpl.ticker.PercentFormatter(xmax=1, decimals=decimals))


def save(fig, name):
    """Save as docs/report-notes/PB-12-<name>.png for the report."""
    path = FIGURE_DIR / f"PB-12-{name}.png"
    fig.savefig(path, dpi=200, bbox_inches="tight", pad_inches=0.2)
    return path.relative_to(FIGURE_DIR.parents[1])


def show(fig, name):
    path = save(fig, name)
    plt.show()
    print(f"Saved {path}")
