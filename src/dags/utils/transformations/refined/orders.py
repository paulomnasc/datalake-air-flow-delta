import pandas as pd

def refinar(df: pd.DataFrame) -> pd.DataFrame:
    # Preenchimento de dados ausentes
    df["comments"] = df["comments"].fillna("Sem observações")
    df["shippeddate"] = pd.to_datetime(df["shippeddate"], errors="coerce")
    df["orderdate"] = pd.to_datetime(df["orderdate"])
    df["requireddate"] = pd.to_datetime(df["requireddate"])

    # Tempo de entrega
    df["tempo_entrega_dias"] = (df["shippeddate"] - df["orderdate"]).dt.days

    # Status curado
    df["status_curado"] = df["status"].replace({
        "Shipped": "Enviado",
        "Cancelled": "Cancelado",
        "Resolved": "Resolvido",
        "On Hold": "Em espera",
        "Disputed": "Em disputa",
        "In Process": "Em processamento"
    })

    # Flag de atraso
    df["atrasado"] = df["shippeddate"] > df["requireddate"]

    # Curadoria final
    df_refinado = df[[
        "ordernumber", "orderdate", "requireddate", "shippeddate",
        "status_curado", "tempo_entrega_dias", "atrasado", "comments", "customernumber"
    ]]

    return df_refinado
