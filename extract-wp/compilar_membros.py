#!/usr/bin/env python3
import os
import re
import csv
from glob import glob

def clean_phone(raw_phone: str):
    """
    Cleans and standardizes a phone number string.
    Returns (e164_formatted, display_formatted) or (None, None) if invalid.
    """
    s = raw_phone.strip()
    if not s or "você" in s.lower() or "grupo" in s.lower():
        return None, None
    
    # Check if string contains digits
    digits = re.sub(r'\D', '', s)
    if len(digits) < 8 or len(digits) > 15:
        return None, None
    
    # Add leading + if missing for international numbers (starting with 55, 244, 258, 234, 62, 595, etc.)
    if not s.startswith("+"):
        e164 = "+" + digits
    else:
        e164 = "+" + digits
        
    return e164, s

def main():
    arquivos_dir = "/root/datalake-air-flow-delta/extract-wp/arquivos"
    csv_files = [
        os.path.join(arquivos_dir, "Grupo Balao Campeao.csv"),
        os.path.join(arquivos_dir, "Grupo Palpites de Futebol.csv"),
        os.path.join(arquivos_dir, "Grupo Oficial de Palpites e Apostas.csv")
    ]
    
    contacts = {} # e164 -> {"origem": list_of_groups, "raw": original_str}

    for file_path in csv_files:
        filename = os.path.basename(file_path).replace(".csv", "")
        if not os.path.exists(file_path):
            print(f"Aviso: Arquivo não encontrado {file_path}")
            continue

        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()

        # Split content by commas, newlines, quotes
        # Regex to capture phone candidates
        candidates = re.findall(r'\+?\d[\d\s-]{7,}\d', content)

        for cand in candidates:
            e164, raw_str = clean_phone(cand)
            if e164:
                if e164 not in contacts:
                    contacts[e164] = {
                        "e164": e164,
                        "raw": cand.strip(),
                        "grupos": set()
                    }
                contacts[e164]["grupos"].add(filename)

    output_csv = os.path.join(arquivos_dir, "todos_membros_whatsapp.csv")
    
    with open(output_csv, "w", encoding="utf-8-sig", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["Telefone (E164)", "Telefone (Original)", "Grupos"])
        
        sorted_contacts = sorted(contacts.values(), key=lambda x: x["e164"])
        for c in sorted_contacts:
            grupos_str = ", ".join(sorted(c["grupos"]))
            writer.writerow([c["e164"], c["raw"], grupos_str])

    print(f"Compilação concluída! Total de contatos únicos extraídos: {len(contacts)}")
    print(f"Salvo em: {output_csv}")

if __name__ == "__main__":
    main()
