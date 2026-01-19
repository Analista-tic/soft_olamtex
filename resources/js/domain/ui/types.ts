import type { AdditionalContact } from '../purchases';

export interface DeleteButtonProps {
    disabled?: boolean;
    onClick: () => void;
}

export interface CreateButtonProps {
    disabled?: boolean;
    onClick: () => void;
}

export interface Branch {
    name: string;
    address: string;
    phone: string;
}

export interface BranchesListProps {
    branches: Branch[];
    onUpdateBranches: (updatedBranches: Branch[]) => void;
}

export interface AdditionalContactsListProps {
    additionalContacts: AdditionalContact[];
    onUpdateAdditionalContacts: (updatedContacts: AdditionalContact[]) => void;
}
