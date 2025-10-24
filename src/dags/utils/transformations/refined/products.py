import pandas as pd

def refinar(df: pd.DataFrame) -> pd.DataFrame:
    # Preenchimento de dados ausentes
    df["productdescription"] = df["productdescription"].fillna("Descrição não disponível")

    # Valor em estoque
    df["estoque_valor"] = df["quantityinstock"] * df["buyprice"]

    # Margem de lucro
    df["margem_lucro"] = (df["msrp"] - df["buyprice"]) / df["buyprice"]

    # Classificação de categoria
    df["categoria_curada"] = df["productline"].replace({
        "Classic Cars": "Carros Clássicos",
        "Motorcycles": "Motos",
        "Trucks and Buses": "Caminhões e Ônibus",
        "Planes": "Aviões",
        "Ships": "Navios",
        "Trains": "Trens",
        "Vintage Cars": "Carros Vintage"
    })

    # Curadoria final
    df_refinado = df[[
        "productcode", "productname", "categoria_curada", "productscale",
        "productvendor", "quantityinstock", "buyprice", "msrp",
        "estoque_valor", "margem_lucro"
    ]]

    return df_refinado
