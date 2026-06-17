#!/usr/bin/env python3
"""Generate an import-ready SEO Enrichment Studio JSON payload from an XLSX report.

Usage:
  python3 tools/generate_enrichment_payload.py enriquecer.xlsx --output ses-enrichment-payload.json

The script intentionally uses only Python's standard library so it can run in the
agent/container without installing spreadsheet dependencies. It reads the first
worksheet, detects URL/slug/title columns, derives the service/profession/city
from programmatic slugs, and writes the same portable JSON format consumed by
SEO Enrichment Studio's Importar/Exportar screen.
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import html
import json
import re
import sys
import zipfile
from pathlib import Path
from typing import Any
from urllib.parse import urlparse
from urllib.request import Request, urlopen
import xml.etree.ElementTree as ET

NS = {
    "main": "http://schemas.openxmlformats.org/spreadsheetml/2006/main",
    "rel": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
    "pkgrel": "http://schemas.openxmlformats.org/package/2006/relationships",
}
SITEMAP_NS = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9"}

DEFAULT_COMPANY = "Studio Privilege"

PREFIXES = {
    "criacao-de-sites-para-": "criação de sites",
    "criação-de-sites-para-": "criação de sites",
    "agencia-de-marketing-digital-em-": "agência de marketing digital",
    "agência-de-marketing-digital-em-": "agência de marketing digital",
    "marketing-digital-em-": "marketing digital",
    "trafego-pago-para-": "tráfego pago",
    "tráfego-pago-para-": "tráfego pago",
    "seo-local-para-": "SEO local",
}

KNOWN_SUFFIXES = ("-agencia-privilege", "-studio-privilege", "-privilege")

CATEGORIES = {
    "saude": ["medico", "médico", "dentista", "psicologo", "psicólogo", "fisioterapeuta", "clinica", "clínica"],
    "beleza": ["salao", "salão", "barbearia", "manicure", "estetica", "estética", "maquiador"],
    "servicos": ["advogado", "contador", "engenheiro", "arquiteto", "eletricista", "encanador", "mecanico", "mecânico"],
    "comercio": ["loja", "boutique", "restaurante", "pizzaria", "mercado", "padaria", "academia"],
}



def normalize_github_url(value: str) -> str:
    """Convert common GitHub blob URLs to direct raw download URLs."""
    parsed = urlparse(value)
    if parsed.netloc == "github.com" and "/blob/" in parsed.path:
        owner_repo, rest = parsed.path.strip("/").split("/blob/", 1)
        branch, file_path = rest.split("/", 1)
        return f"https://raw.githubusercontent.com/{owner_repo}/{branch}/{file_path}"
    return value


def download_xlsx(url: str, destination: Path) -> Path:
    request = Request(normalize_github_url(url), headers={"User-Agent": "SEO-Enrichment-Studio/1.0"})
    with urlopen(request, timeout=60) as response:
        data = response.read()
    destination.write_bytes(data)
    if not zipfile.is_zipfile(destination):
        raise ValueError(f"O arquivo baixado de {url} não parece ser um XLSX válido.")
    return destination

def slugify(value: str) -> str:
    value = value.lower().strip()
    accents = str.maketrans("áàãâäéèêëíìîïóòõôöúùûüçñ", "aaaaaeeeeiiiiooooouuuucn")
    value = value.translate(accents)
    value = re.sub(r"[^a-z0-9]+", "-", value)
    return re.sub(r"-+", "-", value).strip("-")


def strip_known_suffixes(value: str) -> str:
    for suffix in KNOWN_SUFFIXES:
        if value.endswith(suffix):
            return value[: -len(suffix)]
    return value


def humanize(value: str) -> str:
    return re.sub(r"\s+", " ", value.replace("-", " ")).strip()


def read_shared_strings(zf: zipfile.ZipFile) -> list[str]:
    try:
        root = ET.fromstring(zf.read("xl/sharedStrings.xml"))
    except KeyError:
        return []
    strings: list[str] = []
    for si in root.findall("main:si", NS):
        strings.append("".join(t.text or "" for t in si.findall(".//main:t", NS)))
    return strings


def workbook_first_sheet_path(zf: zipfile.ZipFile) -> str:
    workbook = ET.fromstring(zf.read("xl/workbook.xml"))
    first_sheet = workbook.find("main:sheets/main:sheet", NS)
    if first_sheet is None:
        raise ValueError("A planilha não possui abas.")
    rel_id = first_sheet.attrib[f"{{{NS['rel']}}}id"]
    rels = ET.fromstring(zf.read("xl/_rels/workbook.xml.rels"))
    for rel in rels:
        if rel.attrib.get("Id") == rel_id:
            target = rel.attrib["Target"].lstrip("/")
            return target if target.startswith("xl/") else f"xl/{target}"
    raise ValueError("Não foi possível localizar a primeira aba da planilha.")


def cell_value(cell: ET.Element, shared: list[str]) -> str:
    cell_type = cell.attrib.get("t")
    value = cell.find("main:v", NS)
    if cell_type == "inlineStr":
        return "".join(t.text or "" for t in cell.findall(".//main:t", NS)).strip()
    if value is None or value.text is None:
        return ""
    raw = value.text
    if cell_type == "s":
        idx = int(raw)
        return shared[idx].strip() if idx < len(shared) else ""
    return raw.strip()


def read_xlsx(path: Path) -> list[dict[str, str]]:
    with zipfile.ZipFile(path) as zf:
        shared = read_shared_strings(zf)
        sheet = ET.fromstring(zf.read(workbook_first_sheet_path(zf)))
        rows = []
        for row in sheet.findall("main:sheetData/main:row", NS):
            values = []
            for cell in row.findall("main:c", NS):
                values.append(cell_value(cell, shared))
            if any(values):
                rows.append(values)
    if not rows:
        return []
    headers = [slugify(h) or f"coluna-{i+1}" for i, h in enumerate(rows[0])]
    output = []
    for values in rows[1:]:
        record = {headers[i]: values[i].strip() if i < len(values) else "" for i in range(len(headers))}
        if any(record.values()):
            output.append(record)
    return output



def xml_locs(xml_bytes: bytes) -> list[str]:
    root = ET.fromstring(xml_bytes)
    nodes = root.findall(".//sm:loc", SITEMAP_NS) or root.findall(".//loc")
    return [node.text.strip() for node in nodes if node.text and node.text.strip()]


def read_sitemap_source(source: str) -> bytes:
    if source.startswith("http://") or source.startswith("https://"):
        request = Request(source, headers={"User-Agent": "SEO-Enrichment-Studio/1.0"})
        with urlopen(request, timeout=120) as response:
            return response.read()
    return Path(source).read_bytes()


def collect_sitemap_urls(sources: list[str]) -> list[str]:
    seen: dict[str, None] = {}
    pending = list(sources)
    while pending:
        source = pending.pop(0)
        for loc in xml_locs(read_sitemap_source(source)):
            if loc.endswith(".xml"):
                pending.append(loc)
                continue
            seen.setdefault(loc, None)
    return list(seen.keys())


def read_protected_slugs(path: Path | None) -> set[str]:
    if path is None:
        return set()
    protected: set[str] = set()
    for line in path.read_text(encoding="utf-8").splitlines()[1:]:
        parts = line.split("\t")
        if len(parts) >= 3 and parts[2].strip():
            protected.add(slugify(parts[2].strip()))
    return protected


def is_supported_programmatic_url(url: str) -> bool:
    path = urlparse(url).path.strip("/") if url.startswith("http") else url.strip("/")
    slug = slugify(path.split("/")[-1])
    return slug.startswith("criacao-de-sites-para-") or slug.startswith("agencia-de-marketing-digital-em-")


def rows_from_urls(urls: list[str], protected_slugs: set[str], eligible_only: bool) -> list[dict[str, str]]:
    rows = []
    for url in urls:
        path = urlparse(url).path.strip("/") if url.startswith("http") else url.strip("/")
        slug = slugify(path.split("/")[-1])
        if slug in protected_slugs:
            continue
        if eligible_only and not is_supported_programmatic_url(url):
            continue
        rows.append({"url": url})
    return rows

def pick(record: dict[str, str], *needles: str) -> str:
    for key, value in record.items():
        if value and any(needle in key for needle in needles):
            return value
    return ""


def path_from_record(record: dict[str, str]) -> str:
    raw = pick(record, "url", "link", "pagina", "page", "permalink", "caminho", "path", "slug")
    if raw.startswith("http"):
        return urlparse(raw).path.strip("/")
    return raw.strip().strip("/")


def parse_context(path: str, title: str) -> dict[str, str]:
    slug = slugify(path.split("/")[-1] if path else title)
    context = {"service": "presença digital", "profession": humanize(title or slug), "city": "", "category": "serviços locais", "slug": slug}
    for prefix, service in PREFIXES.items():
        clean_prefix = slugify(prefix)
        if slug.startswith(clean_prefix):
            tail = strip_known_suffixes(slug[len(clean_prefix):])
            context["service"] = service
            if "marketing-digital-em" in clean_prefix or "agencia-de-marketing-digital-em" in clean_prefix:
                context["profession"] = "negócios locais"
                context["city"] = humanize(tail).title()
            else:
                markers = list(re.finditer(r"-(em|na|no)-", tail))
                match = markers[-1] if markers else None
                if match:
                    context["profession"] = humanize(tail[: match.start()])
                    context["city"] = humanize(tail[match.end():]).title()
                else:
                    context["profession"] = humanize(tail)
            break
    profession_slug = slugify(context["profession"])
    for category, terms in CATEGORIES.items():
        if any(term in profession_slug for term in map(slugify, terms)):
            context["category"] = category
            break
    return context


def faq(service: str, profession: str, city: str) -> list[dict[str, str]]:
    local = f" em {city}" if city else ""
    return [
        {"q": f"Por que {profession} precisa de uma página específica{local}?", "a": "Porque uma página específica responde melhor às buscas locais, explica o serviço com clareza e reduz dúvidas antes do contato."},
        {"q": "O conteúdo enriquecido substitui o atendimento comercial?", "a": "Não. Ele organiza informações essenciais para preparar o visitante e aumentar a qualidade das conversas pelo WhatsApp ou formulário."},
        {"q": "Quais informações ajudam na conversão?", "a": "Provas visuais, serviços atendidos, região de atendimento, diferenciais, perguntas frequentes e chamadas claras para orçamento."},
        {"q": f"Esse formato ajuda em buscas por {service}?", "a": "Sim, porque combina intenção de busca, contexto do nicho, localização e vocabulário relacionado de forma natural."},
    ]


def build_html(context: dict[str, str], company: str) -> str:
    service = context["service"]
    profession = context["profession"] or "negócios locais"
    city = context["city"]
    local = f" em {city}" if city else " na sua região"
    category = context["category"]
    sections = [
        (f"{service.capitalize()} para {profession}{local}", [
            f"Uma página de {service} para {profession}{local} precisa ir além de um texto genérico. Ela deve explicar o serviço, mostrar contexto local e facilitar a decisão de quem procura uma empresa confiável para iniciar um projeto digital.",
            f"Quando o visitante encontra informações específicas sobre {profession}, exemplos de necessidades comuns e caminhos claros para solicitar orçamento, a página passa a funcionar como um ponto de entrada comercial mais qualificado.",
        ]),
        ("O que a pessoa espera encontrar", [
            f"Quem pesquisa por {service} para {profession} normalmente quer entender preço, prazo, estrutura da página, possibilidades de portfólio, integração com WhatsApp e como o site pode gerar mais contatos.",
            "Responder essas dúvidas dentro da própria página reduz objeções, evita conversas repetitivas e aumenta a confiança antes do primeiro contato.",
        ]),
        ("Elementos que tornam a página mais útil", [
            "A página deve apresentar descrição objetiva do serviço, benefícios para o nicho, diferenciais, perguntas frequentes, chamada para orçamento e uma explicação simples do processo de criação ou otimização.",
            f"Para o segmento de {category}, também é importante usar linguagem próxima da realidade do cliente, com exemplos de demandas, provas de autoridade e argumentos que conectem presença digital a resultado comercial.",
        ]),
        ("Como o enriquecimento melhora SEO e conversão", [
            "O enriquecimento semântico adiciona termos relacionados, contexto de decisão, sinais de localização e respostas que ajudam mecanismos de busca e usuários a entenderem melhor o objetivo da página.",
            "Em vez de depender apenas da troca de cidade ou profissão no título, o conteúdo passa a entregar uma experiência mais completa, com tópicos que diferenciam páginas programáticas entre si.",
        ]),
        ("Próximo passo recomendado", [
            f"O ideal é usar esta página como apoio para captação de contatos qualificados. A chamada final deve direcionar o visitante para WhatsApp, formulário ou uma conversa rápida com {company}.",
        ]),
    ]
    parts = ['<section class="ses-section">']
    for heading, paragraphs in sections:
        parts.append(f"<h2>{html.escape(heading)}</h2>")
        parts.extend(f"<p>{html.escape(p)}</p>" for p in paragraphs)
    parts.append("<h2>Perguntas frequentes</h2><div class=\"ses-faq\">")
    for item in faq(service, profession, city):
        parts.append(f"<details><summary>{html.escape(item['q'])}</summary><p>{html.escape(item['a'])}</p></details>")
    parts.append("</div>")
    parts.append(f"<p class=\"ses-cta\">Fale com {html.escape(company)} para transformar esta página em um canal mais claro, útil e preparado para gerar oportunidades.</p>")
    parts.append("</section>")
    return "".join(parts)


def make_payload(rows: list[dict[str, str]], company: str) -> dict[str, Any]:
    items = []
    for index, row in enumerate(rows, 1):
        path = path_from_record(row)
        title = pick(row, "titulo", "title", "h1", "nome") or humanize(path.split("/")[-1])
        if not path and not title:
            continue
        context = parse_context(path, title)
        enriched_html = build_html(context, company)
        plain = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", enriched_html)).strip().lower()
        city_suffix = f" em {context['city']}" if context["city"] else ""
        items.append({
            "source_id": index,
            "post_type": "page",
            "slug": context["slug"],
            "path": path or context["slug"],
            "title": title,
            "enriched_html": enriched_html,
            "status": "enriquecida",
            "quality_score": 85,
            "similarity_score": 0,
            "content_hash": hashlib.md5(plain.encode()).hexdigest(),
            "yoast_title": f"{context['service'].capitalize()} para {context['profession']}{city_suffix} | {company}",
            "yoast_description": f"Página enriquecida sobre {context['service']} para {context['profession']}{city_suffix}, com contexto, dúvidas frequentes e chamada para orçamento.",
        })
    return {
        "format": "ses-enrichment-portable",
        "version": "offline-generator-1.0.0",
        "site_url": "",
        "generated_at": dt.datetime.now(dt.UTC).replace(microsecond=0).isoformat().replace("+00:00", "Z"),
        "count": len(items),
        "items": items,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Generate SEO Enrichment Studio JSON from an XLSX report.")
    parser.add_argument("xlsx", nargs="?", type=Path, help="Path to the XLSX report with pages to enrich.")
    parser.add_argument("--xlsx-url", help="Optional URL to download the XLSX report before generating the payload. GitHub blob URLs are converted to raw URLs automatically.")
    parser.add_argument("--url-list", type=Path, help="Optional text file with one page URL/path per line to enrich when XLSX is not available.")
    parser.add_argument("--sitemap", action="append", default=[], help="Sitemap XML URL/path or sitemap index URL/path. Can be used multiple times.")
    parser.add_argument("--protected-pages", type=Path, help="TSV exported/created from protected pages with columns id, title and slug. Matching slugs are excluded.")
    parser.add_argument("--all-sitemap-urls", action="store_true", help="Include every non-protected sitemap URL. By default only supported programmatic patterns are enriched.")
    parser.add_argument("--output", "-o", type=Path, default=Path("ses-enrichment-payload.json"), help="JSON output path.")
    parser.add_argument("--company", default=DEFAULT_COMPANY, help="Company name used in CTAs and Yoast titles.")
    args = parser.parse_args()

    xlsx_path = args.xlsx
    if args.xlsx_url:
        xlsx_path = download_xlsx(args.xlsx_url, xlsx_path or Path("enriquecer.xlsx"))
    protected_slugs = read_protected_slugs(args.protected_pages)
    if args.sitemap:
        urls = collect_sitemap_urls(args.sitemap)
        rows = rows_from_urls(urls, protected_slugs, eligible_only=not args.all_sitemap_urls)
    elif args.url_list:
        urls = [line.strip() for line in args.url_list.read_text(encoding="utf-8").splitlines() if line.strip() and not line.lstrip().startswith("#")]
        rows = rows_from_urls(urls, protected_slugs, eligible_only=False)
    else:
        if xlsx_path is None:
            parser.error("provide an XLSX path, --xlsx-url, --url-list, or --sitemap")
        rows = rows_from_urls([path_from_record(row) for row in read_xlsx(xlsx_path)], protected_slugs, eligible_only=False)
    payload = make_payload(rows, args.company)
    args.output.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Generated {payload['count']} enrichment items at {args.output}")
    return 0 if payload["count"] else 2


if __name__ == "__main__":
    sys.exit(main())
