export type VisitError = {
    message: string;
    retry: () => void;
};

/** A failed visit keeps the user on the page (no error page) and offers a retry; `isCurrent` lets only the latest visit report. */
export function failureHandlers(
    message: string,
    retry: () => void,
    setVisitError: (error: VisitError | null) => void,
    isCurrent: () => boolean = () => true,
) {
    const fail = () => {
        if (isCurrent()) {
            setVisitError({ message, retry });
        }

        return false;
    };

    return { onHttpException: fail, onNetworkError: fail };
}
