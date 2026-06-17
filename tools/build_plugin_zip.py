#!/usr/bin/env python3
"""Build SEO Enrichment Studio plugin ZIP from source files.

Binary ZIPs are intentionally not committed. Run this locally when you need the
installable WordPress package:

  python3 tools/build_plugin_zip.py
"""
from __future__ import annotations

import argparse
import zipfile
from pathlib import Path


def build_zip(source: Path, output: Path) -> None:
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(source.rglob("*")):
            arcname = path.as_posix()
            if path.is_dir():
                archive.write(path, arcname + "/")
            else:
                archive.write(path, arcname)


def main() -> int:
    parser = argparse.ArgumentParser(description="Build the SEO Enrichment Studio plugin ZIP.")
    parser.add_argument("--source", type=Path, default=Path("seo"), help="Plugin source directory.")
    parser.add_argument("--output", type=Path, default=Path("seo.zip"), help="ZIP file to create.")
    args = parser.parse_args()
    build_zip(args.source, args.output)
    print(f"Created {args.output} from {args.source}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
