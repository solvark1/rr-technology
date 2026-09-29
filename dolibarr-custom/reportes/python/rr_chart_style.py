"""Shared presentation only: queries and aggregations remain in each report."""
import os
import textwrap
from pathlib import Path
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.ticker import MaxNLocator, StrMethodFormatter
from cycler import cycler

PALETTE = ["#65dbc3", "#70b6ed", "#c0a5f5", "#efbb69", "#f18b99", "#91a6b9"]
plt.rcParams.update({
    "figure.facecolor": "#17222e", "axes.facecolor": "#17222e",
    "savefig.facecolor": "#17222e", "text.color": "#e6edf7",
    "axes.labelcolor": "#b9cadd", "xtick.color": "#b9cadd", "ytick.color": "#dce8f2",
    "axes.edgecolor": "#334657", "grid.color": "#334657", "grid.alpha": .5,
    "axes.spines.top": False, "axes.spines.right": False,
    "font.family": "DejaVu Sans", "font.size": 11, "axes.titlesize": 15,
    "axes.titleweight": "bold", "axes.labelsize": 11, "axes.labelpad": 12,
    "legend.facecolor": "#17222e", "legend.edgecolor": "#334657",
    "legend.fontsize": 10, "axes.prop_cycle": cycler(color=PALETTE),
    "figure.figsize": (10, 5.5), "figure.dpi": 140, "savefig.dpi": 160,
})

def save_chart(path, **kwargs):
    fig = plt.gcf()
    for ax in fig.axes:
        ax.set_title("")  # The surrounding card already names this chart.
        horizontal = any(getattr(c, "orientation", None) == "horizontal" for c in ax.containers)
        axis = ax.xaxis if horizontal else ax.yaxis
        axis.set_major_locator(MaxNLocator(nbins=6, integer=True))
        axis.set_major_formatter(StrMethodFormatter("{x:,.0f}"))
        ax.set_axisbelow(True)
        ax.grid(axis="x" if horizontal else "y", linewidth=.7)
        labels = ax.get_yticklabels() if horizontal else ax.get_xticklabels()
        ticks = ax.get_yticks() if horizontal else ax.get_xticks()
        translations={"good":"Bueno", "worn":"Con desgaste", "damaged":"Dañado", "pending":"Pendiente de diagnóstico", "open":"Abierta", "received":"Recibido · pendiente", "replaced":"Sustituido", "resolved":"Resuelta", "closed":"Cerrada", "cancelled":"Cancelada"}
        texts = [textwrap.fill(translations.get(l.get_text(),l.get_text()), 28 if horizontal else 18) for l in labels]
        if horizontal:
            ax.set_yticks(ticks, texts)
            ax.tick_params(axis="y", length=0, pad=10)
        else:
            ax.set_xticks(ticks, texts, rotation=0, ha="center")
            ax.tick_params(axis="x", length=0, pad=10)
        if not ax.has_data():
            ax.text(.5, .5, "No hay datos para este reporte", transform=ax.transAxes, ha="center", color="#b9cadd")
        ax.margins(x=.08 if horizontal else .06, y=.08)
    fig.set_size_inches(11, max(5.4, min(10, .48 * max([len(a.get_yticklabels()) for a in fig.axes] or [1]))) if any(getattr(c, "orientation", None)=="horizontal" for a in fig.axes for c in a.containers) else 5.8)
    fig.tight_layout(pad=2)
    target=Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    temporary=target.with_name(target.stem + ".tmp.png")
    fig.savefig(temporary, bbox_inches="tight")
    os.replace(temporary,target)
    plt.close(fig)
