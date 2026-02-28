export type DocumentType = {
    id: number;
    code: string;
    name: string;
    is_natural_person: boolean;
    is_legal_person: boolean;
};

export type Eps = {
    id: number;
    code: string;
    name: string;
};

export type PensionFund = {
    id: number;
    code: string;
    name: string;
};

export type SeveranceFund = {
    id: number;
    code: string;
    name: string;
};
