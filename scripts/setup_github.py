#!/usr/bin/env python3
"""
One-time GitHub setup for the FYP repository.

Creates (or reuses, if they already exist):
  1. the GitHub repository, pushing this folder as the first commit
  2. labels for type, epic, priority and role
  3. one milestone per sprint (sprints split evenly between --start and --end)
  4. one issue per backlog item in backlog.json
  5. a GitHub Projects board linked to the repo, with Points / Priority / Epic fields,
     every issue added and set to Todo

Safe to re-run: anything already created is skipped.

Requirements: GitHub CLI (`brew install gh`), logged in with project scope:
    gh auth login
    gh auth refresh -s project

Usage (from the repository folder):
    python3 scripts/setup_github.py --dry-run          # show what would happen
    python3 scripts/setup_github.py                    # do it
    python3 scripts/setup_github.py --start 2026-10-26 --end 2026-12-09  # sprint window
"""
import argparse, datetime as dt, json, os, shutil, subprocess, sys, time

REPO_NAME = "shariah-p2p-platform"
VISIBILITY = "--private"          # use "--public" if your supervisor needs to see it without an invite
DESCRIPTION = ("FYP: Web-based Shariah-compliant P2P financing and SME crowdinvesting platform "
               "with AI-assisted risk analysis (Laravel, React, MySQL, Flask, XGBoost, SHAP)")
PROJECT_TITLE = "FYP Product Backlog"
DEFAULT_START = "2026-10-26"
DEFAULT_END = "2026-12-09"     # a week before the 16 Dec 2026 final deadline

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

LABEL_COLOURS = {
    "type": "1d76db", "epic": "5319e7", "priority:Must": "b60205",
    "priority:Should": "fbca04", "priority:Could": "c2e0c6", "role": "0e8a16",
}

DRY = False


def gh(*args, mutate=False, ok_fail=False, fake=""):
    """Run a gh command and return stdout. In dry-run, mutating calls are printed, not run."""
    cmd = ["gh", *args]
    if DRY and mutate:
        print("   would run:", " ".join(a if " " not in a else repr(a) for a in cmd[:8]),
              "…" if len(cmd) > 8 else "")
        return fake
    if DRY and not mutate and not shutil.which("gh"):
        return fake
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        if ok_fail:
            return None
        sys.exit(f"\n✗ Command failed: {' '.join(cmd[:6])}\n{r.stderr.strip()}")
    return r.stdout.strip()


def git(*args):
    r = subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit(f"\n✗ git {' '.join(args)} failed:\n{r.stderr.strip()}")
    return r.stdout.strip()


def preflight():
    if not shutil.which("gh"):
        if DRY:
            print("! gh is not installed; dry run will continue with placeholders.")
            return "YOUR-GITHUB-USERNAME"
        sys.exit("✗ GitHub CLI not found. Install it with:  brew install gh   then run:  gh auth login")
    if not shutil.which("git"):
        sys.exit("✗ git not found. Install Xcode command line tools:  xcode-select --install")
    login = gh("api", "user", "--jq", ".login", ok_fail=True)
    if not login:
        if DRY:
            print("! Not logged in to gh; dry run will continue with placeholders.")
            return "YOUR-GITHUB-USERNAME"
        sys.exit("✗ Not logged in. Run:  gh auth login")
    if gh("project", "list", "--owner", login, "--limit", "1", ok_fail=True) is None:
        sys.exit("✗ Your gh login lacks the 'project' scope. Run:  gh auth refresh -s project")
    print(f"✓ Logged in to GitHub as {login}")
    return login


def ensure_repo(login):
    full = f"{login}/{REPO_NAME}"
    print(f"\n1. Repository {full}")
    if gh("repo", "view", full, "--json", "name", ok_fail=True) and not DRY:
        print("   exists, reusing")
        return full
    if DRY:
        print(f"   would create {full} ({VISIBILITY.strip('-')}) and push this folder")
        return full
    if not os.path.isdir(os.path.join(ROOT, ".git")):
        git("init", "-b", "main")
    git("add", "-A")
    if subprocess.run(["git", "diff", "--cached", "--quiet"], cwd=ROOT).returncode != 0:
        try:
            git("commit", "-m", "Initial project structure, backlog and Definition of Done")
        except SystemExit:
            sys.exit("✗ git commit failed. Set your identity first:\n"
                     "   git config --global user.name \"Your Name\"\n"
                     "   git config --global user.email \"you@example.com\"")
    gh("repo", "create", REPO_NAME, VISIBILITY, "--description", DESCRIPTION,
       "--source", ROOT, "--remote", "origin", "--push", mutate=True)
    print("   created and pushed")
    return full


def ensure_labels(full, backlog):
    print("\n2. Labels")
    labels = []
    for t, d in [("story", "User story"), ("chore", "Technical task"), ("spike", "Research / investigation"),
                 ("bug", "Defect, including UAT findings")]:
        labels.append((f"type:{t}", LABEL_COLOURS["type"], d))
    for key, name in backlog["epics"].items():
        labels.append((f"epic:{key}", LABEL_COLOURS["epic"], name))
    for p, d in [("Must", "Must have (IR Table 13)"), ("Should", "Should have"), ("Could", "Could have")]:
        labels.append((f"priority:{p}", LABEL_COLOURS[f"priority:{p}"], d))
    for r in ["borrower", "investor", "admin"]:
        labels.append((f"role:{r}", LABEL_COLOURS["role"], f"Affects the {r} portal"))
    for name, colour, desc in labels:
        gh("label", "create", name, "--color", colour, "--description", desc, "--force", "-R", full, mutate=True)
    print(f"   {len(labels)} labels ready")


def ensure_milestones(full, backlog, start, end):
    print("\n3. Sprint milestones")
    existing = set()
    out = gh("api", f"repos/{full}/milestones?state=all&per_page=100", "--jq", ".[].title", ok_fail=True)
    if out:
        existing = set(out.splitlines())
    for i, (name, goal) in enumerate(backlog["sprints"].items()):
        total, n = (end - start).days + 1, len(backlog["sprints"])
        s = start + dt.timedelta(days=round(i * total / n))
        e = start + dt.timedelta(days=round((i + 1) * total / n) - 1)
        label = f"{s:%d %b} – {e:%d %b %Y}"
        if name in existing:
            print(f"   {name} exists")
            continue
        gh("api", "-X", "POST", f"repos/{full}/milestones", "-f", f"title={name}",
           "-f", f"description={goal} ({label})", "-f", f"due_on={e:%Y-%m-%d}T23:59:59Z", mutate=True)
        print(f"   {name}: {label}")


def issue_body(item, epics):
    lines = [f"**{item['id']}** · {epics[item['epic']]} · {item['points']} points · traces to {item['trace']}",
             "", "### Story", item["story"], "", "### Acceptance criteria"]
    lines += [f"- [ ] {a}" for a in item["acceptance"]]
    lines += ["", "### Definition of Done", "See [docs/DEFINITION_OF_DONE.md](../blob/main/docs/DEFINITION_OF_DONE.md)."]
    return "\n".join(lines)


def ensure_issues(full, backlog):
    print("\n4. Backlog issues")
    existing = {}
    out = gh("issue", "list", "-R", full, "--state", "all", "--limit", "500", "--json", "title,url", ok_fail=True)
    if out:
        existing = {i["title"].split(" ")[0]: i["url"] for i in json.loads(out)}
    urls = {}
    for n, item in enumerate(backlog["items"], 1):
        if item["id"] in existing:
            urls[item["id"]] = existing[item["id"]]
            continue
        labels = [f"type:{item['type']}", f"epic:{item['epic']}", f"priority:{item['priority']}"]
        labels += [f"role:{r}" for r in item["roles"]]
        url = gh("issue", "create", "-R", full, "--title", f"{item['id']} {item['title']}",
                 "--body", issue_body(item, backlog["epics"]), "--label", ",".join(labels),
                 "--milestone", item["sprint"], mutate=True, fake=f"https://github.com/{full}/issues/{n}")
        urls[item["id"]] = url
        if not DRY:
            print(f"   {item['id']} → {url}")
            time.sleep(0.8)  # stay under GitHub's content-creation rate limit
    print(f"   {len(urls)} issues ready ({len([k for k in urls if k in existing])} already existed)")
    return urls


def ensure_project(login, full, backlog, urls):
    print("\n5. GitHub Projects board")
    num = pid = None
    out = gh("project", "list", "--owner", login, "--format", "json", "--limit", "100", ok_fail=True)
    if out:
        for p in json.loads(out).get("projects", []):
            if p.get("title") == PROJECT_TITLE:
                num, pid = p["number"], p["id"]
    if num is None:
        out = gh("project", "create", "--owner", login, "--title", PROJECT_TITLE, "--format", "json",
                 mutate=True, fake='{"number": 1, "id": "PVT_x", "url": ""}')
        p = json.loads(out)
        num, pid = p["number"], p["id"]
        print(f"   created project #{num}")
    else:
        print(f"   reusing project #{num}")
    gh("project", "link", str(num), "--owner", login, "--repo", full, mutate=True, ok_fail=True)

    def fields():
        o = gh("project", "field-list", str(num), "--owner", login, "--format", "json", "--limit", "100",
               ok_fail=True, fake='{"fields": []}')
        return {f["name"]: f for f in json.loads(o or '{"fields": []}').get("fields", [])}

    f = fields()
    wanted = [("Points", "NUMBER", None),
              ("Priority", "SINGLE_SELECT", "Must,Should,Could"),
              ("Epic", "SINGLE_SELECT", ",".join(backlog["epics"].values()))]
    for name, kind, opts in wanted:
        if name in f:
            continue
        args = ["project", "field-create", str(num), "--owner", login, "--name", name, "--data-type", kind]
        if opts:
            args += ["--single-select-options", opts]
        gh(*args, mutate=True)
        print(f"   added field {name}")
    f = fields()
    if DRY:
        print(f"   would add {len(urls)} issues to the board and set Status=Todo, Points, Priority, Epic")
        return num

    def opt(field, value):
        for o in f.get(field, {}).get("options", []):
            if o["name"] == value:
                return o["id"]
        return None

    on_board = {}
    o = gh("project", "item-list", str(num), "--owner", login, "--format", "json", "--limit", "1000", ok_fail=True)
    if o:
        for it in json.loads(o).get("items", []):
            c = it.get("content") or {}
            if c.get("url"):
                on_board[c["url"]] = it["id"]

    for item in backlog["items"]:
        url = urls[item["id"]]
        if url in on_board:
            continue
        iid = json.loads(gh("project", "item-add", str(num), "--owner", login, "--url", url,
                            "--format", "json", mutate=True))["id"]
        base = ["project", "item-edit", "--id", iid, "--project-id", pid]
        if "Points" in f:
            gh(*base, "--field-id", f["Points"]["id"], "--number", str(item["points"]), mutate=True, ok_fail=True)
        for field, value in [("Status", "Todo"), ("Priority", item["priority"]),
                             ("Epic", backlog["epics"][item["epic"]])]:
            oid = opt(field, value)
            if field in f and oid:
                gh(*base, "--field-id", f[field]["id"], "--single-select-option-id", oid,
                   mutate=True, ok_fail=True)
        print(f"   {item['id']} on board")
    return num


def main():
    global DRY
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--dry-run", action="store_true", help="print what would be created, change nothing")
    ap.add_argument("--start", default=DEFAULT_START, help="first day of Sprint 1, YYYY-MM-DD")
    ap.add_argument("--end", default=DEFAULT_END, help="last day of the final sprint, YYYY-MM-DD")
    a = ap.parse_args()
    DRY = a.dry_run
    start, end = dt.date.fromisoformat(a.start), dt.date.fromisoformat(a.end)
    if end <= start:
        sys.exit("✗ --end must be after --start")
    with open(os.path.join(ROOT, "backlog.json"), encoding="utf-8") as fh:
        backlog = json.load(fh)
    print(("DRY RUN: nothing will be changed\n" if DRY else "") +
          f"{len(backlog['items'])} backlog items, {sum(i['points'] for i in backlog['items'])} points, "
          f"{len(backlog['sprints'])} sprints, {start:%d %b} – {end:%d %b %Y}")
    login = preflight()
    full = ensure_repo(login)
    ensure_labels(full, backlog)
    ensure_milestones(full, backlog, start, end)
    urls = ensure_issues(full, backlog)
    num = ensure_project(login, full, backlog, urls)
    print(f"\n✓ Done. Repo: https://github.com/{full}")
    print(f"  Board: https://github.com/users/{login}/projects/{num}")
    print("  Finish in the browser: on the board, add a Board view (columns = Status) and a Table view "
          "grouped by Milestone.")


if __name__ == "__main__":
    main()
