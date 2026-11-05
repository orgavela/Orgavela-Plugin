#!/usr/bin/env python3
"""Evidence-only O01..O12 closeout integrity gate. Never accesses the network.

A green CI run means the documentation is internally honest, NOT that an
operational gate, release gate, legal approval or real backup is complete.
"""
from __future__ import annotations

import argparse
import copy
import json
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
MATRIX = ROOT / "docs/operations/O01_O12_EXIT_GATES.json"
PROGRESS = ROOT / "docs/operations/V1_PROGRESS.json"
IDS = [f"O{i:02d}" for i in range(1, 13)]
STATES = {"not-started", "partial", "blocked", "accepted"}


def validate(matrix: dict[str, Any], progress: dict[str, Any]) -> tuple[int, list[str]]:
    errors: list[str] = []
    if matrix.get("schema") != "orgavela-operations-closeout-v1":
        errors.append("Unexpected operations manifest schema")
    if matrix.get("authority") != "docs/operations/V1_PROGRESS.json":
        errors.append("Missing canonical progress source")

    rows = matrix.get("operations", [])
    if not isinstance(rows, list):
        return 0, ["Operations must be a list"]
    ids = [row.get("id") for row in rows if isinstance(row, dict)]
    if len(ids) != 12 or len(set(ids)) != 12 or set(ids) != set(IDS):
        errors.append("Expected exactly O01..O12 once each")
    canonical = {row["id"]: row for row in progress.get("ops", [])
                 if isinstance(row, dict) and "id" in row}
    if set(canonical) != set(IDS):
        errors.append("Canonical progress no longer lists exactly O01..O12")

    accepted_count = 0
    for row in rows:
        if not isinstance(row, dict):
            errors.append("Invalid operations entry")
            continue
        key = row.get("id")
        status = row.get("status")
        accepted = row.get("accepted")
        if status not in STATES or not isinstance(accepted, bool):
            errors.append(f"Invalid state/acceptance for {key}")
            continue
        if canonical.get(key, {}).get("status") != status:
            errors.append(f"Manifest/canonical status mismatch: {key}")
        if not isinstance(row.get("technical_evidence"), list):
            errors.append(f"Evidence must be a list: {key}")
        if not accepted:
            if status == "accepted":
                errors.append(f"False accepted state: {key}")
            if not row.get("missing_exit_evidence"):
                errors.append(f"Unaccepted item without remaining exit requirements: {key}")
            if row.get("approval_record"):
                errors.append(f"Approval record despite unaccepted gate: {key}")
            continue
        accepted_count += 1
        if status != "accepted":
            errors.append(f"Acceptance contradicts status: {key}")
        if row.get("missing_exit_evidence"):
            errors.append(f"Accepted gate still has blockers: {key}")
        if not row.get("technical_evidence"):
            errors.append(f"Accepted gate lacks evidence: {key}")
        if not isinstance(row.get("approval_record"), str) or not row["approval_record"].strip():
            errors.append(f"Accepted gate lacks independent signoff record: {key}")

    metrics = progress.get("measurements", {})
    r_total = metrics.get("release_gates_total")
    r_green = metrics.get("release_gates_formally_green")
    if not isinstance(r_total, int) or r_total != 18:
        errors.append("Unexpected release gate count")
    if not isinstance(r_green, int) or r_green < 0 or r_green > 18:
        errors.append("Invalid release acceptance measurement")

    release_complete = accepted_count == 12 and r_green == 18
    if matrix.get("formal_release_decision") not in {"GO", "NO_GO"}:
        errors.append("Formal release decision must be GO or NO_GO")
    if matrix.get("formal_release_decision") == "GO" and not release_complete:
        errors.append("False GO: O01..O12 and all release gates are not formally accepted")
    if metrics.get("v1_release_accepted") and not release_complete:
        errors.append("Canonical V1 acceptance contradicts open operations")
    if accepted_count == 0 and metrics.get("v1_release_accepted"):
        errors.append("V1 is accepted but zero O gates are complete")

    return accepted_count, errors


def negative_self_test(matrix: dict[str, Any], progress: dict[str, Any]) -> None:
    accepted_count, errors = validate(matrix, progress)
    if errors:
        raise RuntimeError("Fixture baseline is already invalid")
    samples = []

    def case(modify):
        m = copy.deepcopy(matrix)
        p = copy.deepcopy(progress)
        modify(m, p)
        samples.append((m, p))

    case(lambda m, p: m.__setitem__("formal_release_decision", "GO"))
    case(lambda m, p: m["operations"][3].__setitem__("accepted", True))
    case(lambda m, p: m["operations"][3].__setitem__("status", "accepted"))
    case(lambda m, p: m["operations"][3].__setitem__("missing_exit_evidence", []))
    case(lambda m, p: m["operations"].append(copy.deepcopy(m["operations"][0])))
    case(lambda m, p: m["operations"].pop())
    case(lambda m, p: p["measurements"].__setitem__("v1_release_accepted", True))
    case(lambda m, p: p["ops"][7].__setitem__("status", "accepted"))
    for number, (m, p) in enumerate(samples, 1):
        if not validate(m, p)[1]:
            raise RuntimeError(f"Negative test {number} incorrectly accepted a false gate")
    print(f"O01_O12_NEGATIVE_TESTS={len(samples)}-passed")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--self-test", action="store_true")
    ap.add_argument("--strict-release", action="store_true")
    args = ap.parse_args()
    matrix = json.loads(MATRIX.read_text(encoding="utf-8"))
    progress = json.loads(PROGRESS.read_text(encoding="utf-8"))
    count, errors = validate(matrix, progress)
    if errors:
        for message in errors:
            print(f"::error::{message}")
        return 1
    print(f"O01_O12_FORMALLY_ACCEPTED={count}/12")
    print(f"ORGA_VELA_RELEASE_DECISION={matrix['formal_release_decision']}")
    for row in matrix["operations"]:
        print(f"{row['id']}={row['status']}; accepted={str(row['accepted']).lower()}")
    if args.self_test:
        negative_self_test(matrix, progress)
    if args.strict_release and (count < 12 or
                                progress["measurements"]["release_gates_formally_green"] != 18 or
                                matrix["formal_release_decision"] != "GO"):
        print("::error::V1 release blocked: O01..O12 and R01..R18 require formal acceptance.")
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
