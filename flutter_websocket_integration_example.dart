import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'flutter_websocket_provider.dart';
import 'flutter_websocket_widgets.dart';

/// Example of how to integrate WebSocket in your main app
class WebSocketApp extends StatelessWidget {
  final String baseUrl;
  final String authToken;

  const WebSocketApp({
    Key? key,
    required this.baseUrl,
    required this.authToken,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => WebSocketProvider(),
      child: MaterialApp(
        title: 'WebSocket Integration Example',
        home: WebSocketHomePage(
          baseUrl: baseUrl,
          authToken: authToken,
        ),
      ),
    );
  }
}

class WebSocketHomePage extends StatefulWidget {
  final String baseUrl;
  final String authToken;

  const WebSocketHomePage({
    Key? key,
    required this.baseUrl,
    required this.authToken,
  }) : super(key: key);

  @override
  State<WebSocketHomePage> createState() => _WebSocketHomePageState();
}

class _WebSocketHomePageState extends State<WebSocketHomePage> {
  @override
  void initState() {
    super.initState();
    _initializeWebSocket();
  }

  Future<void> _initializeWebSocket() async {
    final webSocketProvider = Provider.of<WebSocketProvider>(context, listen: false);
    await webSocketProvider.initialize(widget.baseUrl, widget.authToken);
    
    // Subscribe to user notifications (replace with actual user ID)
    await webSocketProvider.subscribeToUserNotifications(1);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('WebSocket Integration'),
        actions: [
          const WebSocketStatusIndicator(),
          const SizedBox(width: 8),
          const WebSocketConnectionStatus(),
          const SizedBox(width: 16),
        ],
      ),
      body: Column(
        children: [
          // Notification Badge Example
          Consumer<WebSocketProvider>(
            builder: (context, webSocketProvider, child) {
              return NotificationBadge(
                count: webSocketProvider.unreadNotifications,
                child: const Icon(Icons.notifications, size: 24),
              );
            },
          ),
          
          const SizedBox(height: 20),
          
          // Online Status Example
          const Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              OnlineStatusIndicator(userId: 1),
              OnlineStatusIndicator(userId: 2),
              OnlineStatusIndicator(userId: 3),
            ],
          ),
          
          const SizedBox(height: 20),
          
          // Story Ring Example
          const Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              StoryRing(
                userId: 1,
                imageUrl: 'https://via.placeholder.com/60',
                onTap: null,
              ),
              StoryRing(
                userId: 2,
                imageUrl: 'https://via.placeholder.com/60',
                onTap: null,
              ),
              StoryRing(
                userId: 3,
                imageUrl: 'https://via.placeholder.com/60',
                onTap: null,
              ),
            ],
          ),
          
          const SizedBox(height: 20),
          
          // Live Counts Example
          const Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              LiveLikeCount(postId: 1, initialCount: 42),
              LiveCommentCount(postId: 1, initialCount: 8),
            ],
          ),
          
          const SizedBox(height: 20),
          
          // Message Preview Example
          const LiveMessagePreview(
            roomId: 1,
            type: 'direct',
            lastMessage: 'Hello, how are you?',
            lastMessageTime: null,
            isRead: false,
          ),
          
          const Spacer(),
          
          // Connection Controls
          Consumer<WebSocketProvider>(
            builder: (context, webSocketProvider, child) {
              return Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  ElevatedButton(
                    onPressed: webSocketProvider.isConnected
                        ? () => webSocketProvider.disconnect()
                        : () => webSocketProvider.reconnect(),
                    child: Text(
                      webSocketProvider.isConnected ? 'Disconnect' : 'Connect',
                    ),
                  ),
                  ElevatedButton(
                    onPressed: () => webSocketProvider.updateActivity(),
                    child: const Text('Update Activity'),
                  ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

/// Example of how to use WebSocket in a chat screen
class ChatScreen extends StatefulWidget {
  final int roomId;
  final String type; // 'direct' or 'group'

  const ChatScreen({
    Key? key,
    required this.roomId,
    required this.type,
  }) : super(key: key);

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _subscribeToMessages();
  }

  Future<void> _subscribeToMessages() async {
    final webSocketProvider = Provider.of<WebSocketProvider>(context, listen: false);
    
    if (widget.type == 'direct') {
      await webSocketProvider.subscribeToDirectMessages(widget.roomId);
    } else {
      await webSocketProvider.subscribeToGroupMessages(widget.roomId);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Chat ${widget.type}'),
        actions: const [
          WebSocketStatusIndicator(),
        ],
      ),
      body: Column(
        children: [
          // Messages List
          Expanded(
            child: Consumer<WebSocketProvider>(
              builder: (context, webSocketProvider, child) {
                final messages = webSocketProvider.recentMessages
                    .where((msg) => msg['room_id'] == widget.roomId)
                    .toList();

                return ListView.builder(
                  controller: _scrollController,
                  itemCount: messages.length,
                  itemBuilder: (context, index) {
                    final message = messages[index];
                    return ListTile(
                      title: Text(message['content'] ?? ''),
                      subtitle: Text(message['sender_name'] ?? ''),
                      trailing: Text(
                        _formatTime(DateTime.parse(message['created_at'])),
                      ),
                    );
                  },
                );
              },
            ),
          ),
          
          // Message Input
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _messageController,
                    decoration: const InputDecoration(
                      hintText: 'Type a message...',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  onPressed: _sendMessage,
                  icon: const Icon(Icons.send),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _sendMessage() {
    final message = _messageController.text.trim();
    if (message.isEmpty) return;

    // Here you would send the message to your API
    // For now, we'll just clear the input
    _messageController.clear();
  }

  String _formatTime(DateTime time) {
    return '${time.hour}:${time.minute.toString().padLeft(2, '0')}';
  }

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }
}

/// Example of how to use WebSocket in a post screen
class PostScreen extends StatefulWidget {
  final int postId;

  const PostScreen({
    Key? key,
    required this.postId,
  }) : super(key: key);

  @override
  State<PostScreen> createState() => _PostScreenState();
}

class _PostScreenState extends State<PostScreen> {
  int _likeCount = 0;
  int _commentCount = 0;

  @override
  void initState() {
    super.initState();
    _subscribeToPostUpdates();
  }

  Future<void> _subscribeToPostUpdates() async {
    final webSocketProvider = Provider.of<WebSocketProvider>(context, listen: false);
    await webSocketProvider.subscribeToPostUpdates(widget.postId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Post'),
        actions: const [
          WebSocketStatusIndicator(),
        ],
      ),
      body: Column(
        children: [
          // Post content would go here
          const Expanded(
            child: Center(
              child: Text('Post content...'),
            ),
          ),
          
          // Like and Comment buttons with live counts
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                Row(
                  children: [
                    IconButton(
                      onPressed: _toggleLike,
                      icon: const Icon(Icons.favorite),
                    ),
                    LiveLikeCount(
                      postId: widget.postId,
                      initialCount: _likeCount,
                    ),
                  ],
                ),
                Row(
                  children: [
                    IconButton(
                      onPressed: _showComments,
                      icon: const Icon(Icons.comment),
                    ),
                    LiveCommentCount(
                      postId: widget.postId,
                      initialCount: _commentCount,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _toggleLike() {
    setState(() {
      _likeCount++;
    });
    // Here you would send the like to your API
  }

  void _showComments() {
    // Navigate to comments screen
  }
}
