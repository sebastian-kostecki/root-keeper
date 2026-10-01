import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    onSidebar = false,
}: {
    user: User;
    showEmail?: boolean;
    onSidebar?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback
                    className={cn(
                        'rounded-lg',
                        onSidebar
                            ? 'bg-sidebar-accent text-sidebar-accent-foreground group-hover:bg-background group-data-[state=open]:bg-background'
                            : 'bg-muted text-foreground',
                    )}
                >
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span
                        title={user.email}
                        className={cn(
                            'truncate text-xs',
                            onSidebar
                                ? 'text-sidebar-foreground/70'
                                : 'text-muted-foreground',
                        )}
                    >
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
