import pandas as pd

def refinar(df: pd.DataFrame) -> pd.DataFrame:
    df['creditLimit'] = df['creditlimit'].fillna(0)
    df['state'] = df['state'].replace('', pd.NA).fillna('N/A')
    df['salesrepemployeenumber'] = df['salesrepemployeenumber'].fillna(0)

    df['nome_completo'] = df['contactfirstname'].str.strip() + ' ' + df['contactlastname'].str.strip()
    df['valor_cliente'] = df['creditLimit']

    df['faixa_credito'] = pd.cut(
        df['valor_cliente'],
        bins=[-1, 50000, 100000, 150000, float('inf')],
        labels=['Baixo', 'Médio', 'Alto', 'Premium']
    )

    taxas = {
        'USA': 5.0, 'France': 5.3, 'Germany': 5.4, 'UK': 6.2,
        'Japan': 0.035, 'Canada': 3.8, 'Australia': 3.2, 'Spain': 5.1
    }
    df['taxa_cambio'] = df['country'].str.strip().map(taxas)
    df['credito_brl'] = df['valor_cliente'] * df['taxa_cambio']

    return df[[
        'customernumber', 'nome_completo', 'country', 'state',
        'valor_cliente', 'faixa_credito', 'credito_brl'
    ]]
