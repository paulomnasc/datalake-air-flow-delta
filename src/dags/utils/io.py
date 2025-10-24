import pandas as pd

def ler_trusted(tabela):
    caminho = f"/datalake/trusted/{tabela}.parquet"
    return pd.read_parquet(caminho)

def salvar_refined(df, tabela):
    timestamp = pd.Timestamp.now().strftime("%Y%m%d_%H%M%S")
    caminho = f"/datalake/processed/refined/{tabela}_{timestamp}.parquet"
    df.to_parquet(caminho, index=False)
