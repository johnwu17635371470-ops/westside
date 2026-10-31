#!/usr/bin/env python3
"""Offline, insertion-only guide navigation patcher (Python standard library).

Dry run (default, no writes):
  python patch-guide-navigation.py --guide ring --input CURRENT-GUIDE.html
Explicit new output file (existing files and in-place writes are refused):
  python patch-guide-navigation.py --guide ring --input CURRENT-GUIDE.html \
      --apply --output PATCHED-GUIDE.html
Controlled UTF-8 stdin/stdout:
  python patch-guide-navigation.py --guide ring --input - --apply --output -

Reads only the explicitly supplied HTML and sibling guide-navigation.json.
No networking, subprocesses, WordPress/database access, imports or deployment.
Historical dist pages may test structure; never use them to replace live copy.
"""

import argparse
import hashlib
import html
import json
import re
import sys
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit


MAX_HTML_BYTES = 2_000_000
VOID_TAGS = frozenset("area base br col embed hr img input link meta param source track wbr".split())


class PatchError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise PatchError(message)


def normalized(value):
    return " ".join(value.split())


class Element:
    def __init__(self, tag, attrs, start, start_end, parent):
        self.tag = tag
        self.attrs = dict(attrs)
        self.start = start
        self.start_end = start_end
        self.parent = parent
        self.end_start = None
        self.end = None
        self.text_parts = []

    @property
    def text(self):
        return normalized(" ".join(self.text_parts))

    def within(self, ancestor):
        current = self.parent
        while current is not None:
            if current is ancestor:
                return True
            current = current.parent
        return False


class Document(HTMLParser):
    """Use parser semantics for matching and raw offsets for all insertions."""

    def __init__(self, source):
        super().__init__(convert_charrefs=True)
        self.source = source
        self.line_starts = [0] + [match.end() for match in re.finditer("\n", source)]
        self.elements = []
        self.stack = []
        self.feed(source)
        self.close()
        require(not self.stack, "Unclosed HTML elements; inspect the supplied current source.")
        self.ids = {}
        for element in self.elements:
            identifier = element.attrs.get("id")
            if identifier is not None:
                require(identifier and identifier not in self.ids, "Empty or duplicate existing HTML ID.")
                self.ids[identifier] = element

    def source_offset(self):
        line, column = self.getpos()
        return self.line_starts[line - 1] + column

    def handle_starttag(self, tag, attrs):
        require(len(attrs) == len({name for name, _ in attrs}), "Duplicate HTML attributes are not accepted.")
        start = self.source_offset()
        raw = self.get_starttag_text()
        require(self.source[start:start + len(raw)] == raw, "HTML parser offset mismatch.")
        element = Element(tag, attrs, start, start + len(raw), self.stack[-1] if self.stack else None)
        self.elements.append(element)
        if tag in VOID_TAGS:
            element.end_start = element.end = element.start_end
        else:
            self.stack.append(element)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID_TAGS:
            element = self.stack.pop()
            element.end_start = element.end = element.start_end

    def handle_endtag(self, tag):
        require(self.stack and self.stack[-1].tag == tag, "Unbalanced HTML closing tag: " + tag)
        match = re.match(r"</\s*" + re.escape(tag) + r"\s*>", self.source[self.source_offset():], re.I)
        require(match is not None, "Unexpected closing-tag syntax.")
        element = self.stack.pop()
        element.end_start = self.source_offset()
        element.end = self.source_offset() + match.end()

    def handle_data(self, value):
        for element in self.stack:
            element.text_parts.append(value)


def one(elements, description):
    require(len(elements) == 1, description + " must match exactly once (found " + str(len(elements)) + ").")
    return elements[0]


def load_manifest():
    path = Path(__file__).with_name("guide-navigation.json")
    manifest = json.loads(path.read_bytes().decode("utf-8"))
    require(manifest.get("formatVersion") == "fubao-guide-navigation-v1", "Unexpected navigation manifest version.")
    require(set(manifest.get("guides", {})) == {"ring", "necklace", "bracelet", "earring"}, "Expected four reviewed guides.")
    products = manifest.get("productDeepLinks", [])
    require(len(products) == 18 and len({item["asin"] for item in products}) == 18, "Expected eighteen unique product mappings.")
    for item in products:
        require(item["guide"] in manifest["guides"], "Unknown product guide.")
        require(re.fullmatch(r"[A-Z0-9]{10}", item["asin"]), "Invalid source identifier.")
        require(item["guidePath"] == manifest["guides"][item["guide"]]["path"], "Product guide-path mismatch.")
        require(item["deepLink"] == "https://fubaojewelry.com" + item["guidePath"] + "#" + item["anchorId"], "Unexpected product deep link.")
    return manifest


def product_asin(element):
    if element.tag != "a" or not element.attrs.get("href"):
        return None
    try:
        value = urlsplit(element.attrs["href"])
    except ValueError:
        return None
    if value.scheme not in {"", "http", "https"}:
        return None
    if value.netloc and value.hostname not in {"fubaojewelry.com", "localhost", "127.0.0.1"}:
        return None
    match = re.search(r"(?:^|/)products/([a-z0-9]{10})\.html$", value.path, re.I)
    return match.group(1).upper() if match else None


def navigation_html(guide):
    faq_by_id = {item["id"]: item for item in guide["faqs"]}
    choices = guide["navigationFaqIds"]
    require(len(choices) == 2 and len(set(choices)) == 2 and all(key in faq_by_id for key in choices), "Expected two existing intent-related FAQ links.")
    links = [(guide["comparison"]["id"], guide["comparison"]["navLabel"]),
             (guide["questions"]["id"], guide["questions"]["navLabel"])]
    links += [(key, faq_by_id[key]["summary"]) for key in choices]
    items = "".join('<li><a href="#' + html.escape(key, quote=True) + '">' + html.escape(label) + '</a></li>' for key, label in links)
    return ('<nav id="' + html.escape(guide["navigationId"], quote=True) +
            '" class="guide-on-this-page" aria-label="On this page"><p><strong>On this page</strong></p><ul>' +
            items + '</ul></nav>')


def plan_insertions(source, guide_key, manifest):
    require(guide_key in manifest["guides"], "Choose one reviewed guide key.")
    guide = manifest["guides"][guide_key]
    document = Document(source)
    main = one([item for item in document.elements if item.tag == "main"], "Guide main")
    require("ring-guide" in (main.attrs.get("class") or "").split(), "Expected the existing guide main class.")
    elements = [item for item in document.elements if item.within(main)]
    h1 = one([item for item in elements if item.tag == "h1"], "Guide H1")
    require(h1.text == guide["h1Text"], "Guide identity/H1 changed; review current source first.")
    headings = {}
    for key in ("comparison", "questions"):
        headings[key] = one([item for item in elements if item.tag == "h2" and item.text == guide[key]["text"]], key + " heading")
    require(h1.end <= headings["comparison"].start < headings["questions"].start, "Unexpected guide section order.")
    edits = []
    targets = []

    def ensure_id(element, identifier):
        require(re.fullmatch(r"[a-z][a-z0-9-]*", identifier), "Unsafe manifest ID.")
        current = element.attrs.get("id")
        if current is not None:
            require(current == identifier, "Target already has another ID; refusing to replace it.")
        else:
            require(identifier not in document.ids, "Planned ID already belongs to another element.")
            raw = source[element.start:element.start_end]
            require(raw.endswith(">") and not raw.endswith("/>"), "Target opening tag must not self-close.")
            edits.append((element.start_end - 1, ' id="' + identifier + '"'))
        targets.append(identifier)

    ensure_id(headings["comparison"], guide["comparison"]["id"])
    ensure_id(headings["questions"], guide["questions"]["id"])
    details = [item for item in elements if item.tag == "details"]
    require(len(details) == len(guide["faqs"]), "FAQ count changed; review rather than patching a partial match.")
    matched_details = set()
    for faq in guide["faqs"]:
        matches = []
        for detail in details:
            summary = one([item for item in elements if item.tag == "summary" and item.parent is detail], "FAQ summary")
            if summary.text == faq["summary"]:
                matches.append(detail)
        detail = one(matches, "FAQ: " + faq["summary"])
        require(detail.start >= headings["questions"].end and detail not in matched_details, "Unexpected FAQ location or duplicate summary.")
        matched_details.add(detail)
        ensure_id(detail, faq["id"])
    products = [item for item in manifest["productDeepLinks"] if item["guide"] == guide_key]
    if guide_key == "earring":
        require(len(products) == 1 and products[0]["targetElement"] == "dl", "Expected the one existing earring fact list.")
        product = products[0]
        link = one([item for item in elements if product_asin(item) == product["asin"]], "Earring identity link")
        require(link.start < headings["comparison"].start, "Expected the existing product introduction before source facts.")
        facts = one([item for item in elements if item.tag == "dl" and "facts" in (item.attrs.get("class") or "").split()
                     and headings["comparison"].end <= item.start < headings["questions"].start], "Earring fact list")
        ensure_id(facts, product["anchorId"])
    else:
        tbody = one([item for item in elements if item.tag == "tbody" and headings["comparison"].end <= item.start < headings["questions"].start], "Comparison tbody")
        rows = [item for item in elements if item.tag == "tr" and item.parent is tbody]
        require(len(rows) == len(products), "Product row count changed.")
        matched_rows = set()
        for product in products:
            require(product["targetElement"] == "tr", "Expected existing comparison rows.")
            link = one([item for item in elements if product_asin(item) == product["asin"] and item.within(tbody)], "Product row link: " + product["asin"])
            row = one([item for item in rows if link.within(item)], "Product row: " + product["asin"])
            require(row not in matched_rows, "Two source identifiers matched the same row.")
            row_links = [item for item in elements if item.within(row) and product_asin(item)]
            require(len(row_links) == 1, "A product row must have one unambiguous local product link.")
            matched_rows.add(row)
            ensure_id(row, product["anchorId"])
    nav = navigation_html(guide)
    existing_nav = document.ids.get(guide["navigationId"])
    nav_insertion = "\n" + nav + "\n"
    if existing_nav is None:
        edits.append((h1.end, nav_insertion))
    else:
        require(existing_nav.tag == "nav" and existing_nav.within(main), "Navigation ID is attached to another element.")
        # Windows text-file writes may turn our two LF boundaries into CRLF.
        # Accept only that transport difference; keep the nav core and position exact.
        boundary = source[h1.end:existing_nav.start]
        require(boundary in {"\n", "\r\n"}
                and source[existing_nav.start:existing_nav.end] == nav
                and source.startswith(boundary, existing_nav.end),
                "Existing navigation differs or moved; inspect without overwriting.")
    require(len(targets) == len(set(targets)), "Manifest target IDs collide.")
    return edits, {"guide": guide_key, "path": guide["path"], "navigationLinks": 4,
                   "faqTargets": len(details), "productTargets": len(products), "targetIds": targets,
                   "productDeepLinks": products}


def patch_html(source, guide_key, manifest=None):
    """Return patched HTML and an audit summary; never perform file writes."""
    require("\x00" not in source, "NUL bytes are not valid controlled guide HTML.")
    manifest = manifest if manifest is not None else load_manifest()
    edits, summary = plan_insertions(source, guide_key, manifest)
    require(len({position for position, _ in edits}) == len(edits), "Insertion offsets collide.")
    output = source
    for position, addition in sorted(edits, reverse=True):
        output = output[:position] + addition + output[position:]
    # Insertion-only proof: deleting only these additions must recover the input.
    locations = []
    shift = 0
    for position, addition in sorted(edits):
        locations.append((position + shift, addition))
        shift += len(addition)
    restored = output
    for position, addition in reversed(locations):
        require(restored[position:position + len(addition)] == addition, "Preservation proof failed.")
        restored = restored[:position] + restored[position + len(addition):]
    require(restored == source, "Original source changed outside the additions.")
    second_edits, _ = plan_insertions(output, guide_key, manifest)
    require(not second_edits, "Idempotency check failed.")
    summary.update({"changed": bool(edits), "insertions": len(edits), "originalTextPreserved": True,
                    "idempotent": True,
                    "inputSha256": hashlib.sha256(source.encode("utf-8")).hexdigest(),
                    "outputSha256": hashlib.sha256(output.encode("utf-8")).hexdigest()})
    return output, summary


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--guide", required=True, choices=("ring", "necklace", "bracelet", "earring"))
    parser.add_argument("--input", required=True, help="Explicit UTF-8 HTML file, or - for controlled stdin")
    parser.add_argument("--apply", action="store_true", help="Opt in to writing a new output file or HTML stdout")
    parser.add_argument("--output", help="Required with --apply: new file path, or - for HTML stdout")
    args = parser.parse_args()
    if args.apply != bool(args.output):
        parser.error("Dry run is the default. Writing requires both --apply and an explicit --output.")
    try:
        require("://" not in args.input and (not args.output or "://" not in args.output), "Inputs/outputs are local paths or controlled stdin/stdout, never URLs.")
        if args.input == "-":
            raw = sys.stdin.buffer.read(MAX_HTML_BYTES + 1)
        else:
            with Path(args.input).open("rb") as source_file:
                raw = source_file.read(MAX_HTML_BYTES + 1)
        require(len(raw) <= MAX_HTML_BYTES, "HTML input exceeds the 2MB safety bound.")
        output, summary = patch_html(raw.decode("utf-8"), args.guide)
        summary["mode"] = "apply" if args.apply else "dry-run"
        if args.apply:
            if args.output == "-":
                sys.stdout.buffer.write(output.encode("utf-8"))
            else:
                output_path = Path(args.output)
                require(args.input == "-" or output_path.resolve() != Path(args.input).resolve(), "In-place writes are refused; use a new explicit output file.")
                with output_path.open("xb") as target_file:
                    target_file.write(output.encode("utf-8"))
        report_stream = sys.stderr if args.apply and args.output == "-" else sys.stdout
        print(json.dumps(summary, ensure_ascii=True, indent=2), file=report_stream)
    except (PatchError, OSError, UnicodeError, json.JSONDecodeError, KeyError, TypeError) as error:
        print("Guide navigation refused: " + str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
