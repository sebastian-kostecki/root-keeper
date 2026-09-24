export type Scan = {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    has_thumbnail: boolean;
    uploaded_at: string;
};
