#!/usr/bin/env python3
"""
Export all data from a Prometheus instance in OpenMetrics text format.

Queries every metric at native resolution (step = scrape interval) in
time-windowed chunks to avoid overloading the server.

Output format: OpenMetrics 1.0 text — importable via:
  promtool tsdb create-blocks-from openmetrics <file> [--max-block-duration=2h]

Usage:
  python3 prometheus-export.py [--url URL] [--output FILE] [--step SECONDS]
                                [--window HOURS] [--delay SECONDS]
"""

import argparse
import json
import sys
import time
import urllib.parse
import urllib.request
from collections import defaultdict
from datetime import datetime, timezone


def api_get(base_url, path, params=None, retries=5):
    url = f"{base_url}{path}"
    if params:
        url += "?" + urllib.parse.urlencode(params)
    for attempt in range(retries):
        try:
            req = urllib.request.Request(url)
            with urllib.request.urlopen(req, timeout=120) as resp:
                data = json.loads(resp.read().decode())
                if data.get("status") != "success":
                    raise RuntimeError(f"API error: {data}")
                return data["data"]
        except Exception as e:
            if attempt == retries - 1:
                raise
            wait = min(2 ** attempt, 30)
            print(f"  Retry {attempt+1}/{retries} for {path}: {e} (waiting {wait}s)", file=sys.stderr)
            time.sleep(wait)


def discover_time_range(base_url):
    """Find the earliest and latest data points by probing backwards."""
    now = datetime.now(timezone.utc)
    end_ts = now.strftime("%Y-%m-%dT%H:%M:%SZ")

    # Probe backwards in increasing steps to find where data starts
    # Start with 30 days (beyond typical retention) and narrow down
    probe_days = [30, 20, 15, 10, 7, 5, 3, 2, 1]
    min_ts = float("inf")
    max_ts = 0

    for days in probe_days:
        start = now.timestamp() - (days * 86400)
        start_str = datetime.utcfromtimestamp(start).strftime("%Y-%m-%dT%H:%M:%SZ")
        try:
            data = api_get(base_url, "/api/v1/query_range", {
                "query": "up",
                "start": start_str,
                "end": end_ts,
                "step": "3600",
            })
            for result in data.get("result", []):
                for ts, _ in result.get("values", []):
                    min_ts = min(min_ts, float(ts))
                    max_ts = max(max_ts, float(ts))
            if min_ts < float("inf"):
                break
        except Exception:
            continue

    if min_ts == float("inf"):
        raise RuntimeError("Could not find any data in Prometheus")

    return min_ts, max_ts


def discover_metrics(base_url):
    """Get all metric names."""
    data = api_get(base_url, "/api/v1/label/__name__/values")
    return sorted(data)


def discover_series(base_url, metric_name, start, end):
    """Get all label combinations for a metric."""
    data = api_get(base_url, "/api/v1/series", {
        "match[]": metric_name,
        "start": start,
        "end": end,
    })
    return data


def get_metric_type(base_url, metric_name):
    """Try to determine metric type from metadata."""
    try:
        data = api_get(base_url, "/api/v1/metadata", {"metric": metric_name})
        if data and metric_name in data:
            entries = data[metric_name]
            if entries:
                return entries[0].get("type", "unknown"), entries[0].get("help", "")
    except Exception:
        pass
    return "unknown", ""


def format_labels(metric):
    """Format label set as {key="value",...} string, excluding __name__."""
    labels = {k: v for k, v in sorted(metric.items()) if k != "__name__"}
    if not labels:
        return ""
    parts = []
    for k, v in labels.items():
        escaped = v.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")
        parts.append(f'{k}="{escaped}"')
    return "{" + ",".join(parts) + "}"


def openmetrics_type(prom_type):
    type_map = {
        "counter": "counter",
        "gauge": "gauge",
        "histogram": "histogram",
        "summary": "summary",
        "info": "info",
        "stateset": "stateset",
        "unknown": "unknown",
        "untyped": "unknown",
    }
    return type_map.get(prom_type, "unknown")


def export_metric(base_url, metric_name, start, end, step, window_seconds, delay, outfile):
    """Export a single metric across the full time range."""
    series_list = discover_series(base_url, metric_name, start, end)
    if not series_list:
        return 0

    prom_type, help_text = get_metric_type(base_url, metric_name)
    om_type = openmetrics_type(prom_type)

    all_samples = []

    window_start = start
    while window_start < end:
        window_end = min(window_start + window_seconds, end)

        try:
            data = api_get(base_url, "/api/v1/query_range", {
                "query": metric_name,
                "start": window_start,
                "end": window_end,
                "step": step,
            })
        except Exception as e:
            print(f"  ERROR querying {metric_name} [{window_start}-{window_end}]: {e}", file=sys.stderr)
            window_start = window_end
            continue

        for result in data.get("result", []):
            metric_labels = result["metric"]
            label_str = format_labels(metric_labels)
            for ts, val in result.get("values", []):
                all_samples.append((float(ts), label_str, val))

        window_start = window_end
        if delay > 0:
            time.sleep(delay)

    if not all_samples:
        return 0

    # Deduplicate (overlapping windows) and sort by timestamp
    seen = set()
    unique_samples = []
    for ts, labels, val in all_samples:
        key = (ts, labels)
        if key not in seen:
            seen.add(key)
            unique_samples.append((ts, labels, val))
    unique_samples.sort(key=lambda x: (x[0], x[1]))

    # Write OpenMetrics block
    if help_text:
        outfile.write(f"# HELP {metric_name} {help_text}\n")
    outfile.write(f"# TYPE {metric_name} {om_type}\n")

    for ts, labels, val in unique_samples:
        # OpenMetrics requires timestamp in seconds with optional decimal
        ts_str = f"{ts:.3f}"
        # For counters, OpenMetrics expects _total suffix
        name = metric_name
        if om_type == "counter" and not name.endswith("_total") and not name.endswith("_created"):
            name = f"{name}_total"
        outfile.write(f"{name}{labels} {val} {ts_str}\n")

    return len(unique_samples)


def main():
    parser = argparse.ArgumentParser(description="Export all Prometheus data in OpenMetrics format")
    parser.add_argument("--url", default="http://rapunzel.mintopia.net:9090",
                        help="Prometheus base URL (default: http://rapunzel.mintopia.net:9090)")
    parser.add_argument("--output", "-o", default="prometheus-export.om",
                        help="Output file path (default: prometheus-export.om)")
    parser.add_argument("--step", type=int, default=15,
                        help="Query step in seconds — match scrape interval for highest fidelity (default: 15)")
    parser.add_argument("--window", type=float, default=2,
                        help="Query window size in hours — smaller = less memory per query (default: 2)")
    parser.add_argument("--delay", type=float, default=0.1,
                        help="Delay between API calls in seconds (default: 0.1)")
    parser.add_argument("--metrics", nargs="*",
                        help="Export only these metric names (default: all)")
    parser.add_argument("--exclude-internal", action="store_true",
                        help="Exclude prometheus_*, scrape_*, up metrics")
    args = parser.parse_args()

    base_url = args.url.rstrip("/")
    window_seconds = args.window * 3600

    print(f"Prometheus exporter — {base_url}", file=sys.stderr)
    print(f"Step: {args.step}s | Window: {args.window}h | Delay: {args.delay}s", file=sys.stderr)
    print(file=sys.stderr)

    # Discover time range
    print("Discovering data time range...", file=sys.stderr)
    min_ts, max_ts = discover_time_range(base_url)
    print(f"  Data range: {datetime.utcfromtimestamp(min_ts)} → {datetime.utcfromtimestamp(max_ts)}", file=sys.stderr)
    print(f"  Duration: {(max_ts - min_ts) / 3600:.1f} hours", file=sys.stderr)
    print(file=sys.stderr)

    # Discover metrics
    print("Discovering metrics...", file=sys.stderr)
    all_metrics = discover_metrics(base_url)
    print(f"  Found {len(all_metrics)} metric names", file=sys.stderr)

    if args.metrics:
        all_metrics = [m for m in all_metrics if m in args.metrics]
        print(f"  Filtered to {len(all_metrics)} requested metrics", file=sys.stderr)

    if args.exclude_internal:
        internal_prefixes = ("prometheus_", "scrape_", "promhttp_")
        internal_exact = {"up"}
        all_metrics = [m for m in all_metrics
                       if m not in internal_exact
                       and not any(m.startswith(p) for p in internal_prefixes)]
        print(f"  After excluding internals: {len(all_metrics)} metrics", file=sys.stderr)

    print(file=sys.stderr)

    # Export
    total_samples = 0
    started = time.time()

    with open(args.output, "w") as outfile:
        for i, metric_name in enumerate(all_metrics, 1):
            print(f"[{i}/{len(all_metrics)}] {metric_name}...", file=sys.stderr, end=" ")
            sys.stderr.flush()

            count = export_metric(
                base_url, metric_name,
                min_ts, max_ts,
                args.step, window_seconds, args.delay,
                outfile
            )
            total_samples += count
            print(f"{count} samples", file=sys.stderr)

        # OpenMetrics requires EOF marker
        outfile.write("# EOF\n")

    elapsed = time.time() - started
    file_size_mb = 0
    try:
        import os
        file_size_mb = os.path.getsize(args.output) / (1024 * 1024)
    except Exception:
        pass

    print(file=sys.stderr)
    print(f"Export complete!", file=sys.stderr)
    print(f"  File: {args.output} ({file_size_mb:.1f} MB)", file=sys.stderr)
    print(f"  Total samples: {total_samples:,}", file=sys.stderr)
    print(f"  Elapsed: {elapsed:.0f}s", file=sys.stderr)
    print(file=sys.stderr)
    print(f"To import into another Prometheus instance:", file=sys.stderr)
    print(f"  promtool tsdb create-blocks-from openmetrics {args.output}", file=sys.stderr)


if __name__ == "__main__":
    main()
